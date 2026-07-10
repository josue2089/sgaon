<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\User;
use App\Support\FinanceReconcile;
use App\Support\FinanceSummary;
use App\Support\PaymentCurrencyConverter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceSummaryExportService
{
    /**
     * @return StreamedResponse|Response
     */
    public function export(
        string $report,
        string $format,
        ?User $user,
        ?Carbon $startDate,
        ?Carbon $endDate,
        string $currency,
        ?int $campusId,
        array $summary = [],
        float $vesRate = 0,
    ) {
        $dataset = match ($report) {
            'charges' => $this->chargesDataset($user, $startDate, $endDate, $currency, $campusId),
            'payments' => $this->paymentsDataset($user, $startDate, $endDate, $currency, $campusId),
            'projection' => $this->projectionDataset($summary, $currency, $vesRate),
            default => abort(404),
        };

        $meta = $this->buildMeta($report, $startDate, $endDate, $currency, $campusId, $summary);

        return match ($format) {
            'csv' => $this->csvResponse($dataset, $meta['filename'].'.csv'),
            'xlsx' => $this->xlsxResponse($dataset, $meta['filename'].'.xlsx'),
            'pdf' => $this->pdfResponse($dataset, $meta),
            default => abort(404),
        };
    }

    /**
     * @return array{title: string, filename: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function chargesDataset(
        ?User $user,
        ?Carbon $startDate,
        ?Carbon $endDate,
        string $currency,
        ?int $campusId,
    ): array {
        $charges = FinanceSummary::chargesQuery($user, $startDate, $endDate, $currency, $campusId)
            ->with(['student', 'campus', 'course', 'group', 'period'])
            ->orderBy('created_at')
            ->get();

        $rows = $charges->map(function (Charge $charge) use ($currency) {
            $paid = FinanceReconcile::paidTotalForCharge($charge);

            return [
                $charge->campus->name ?? '',
                $charge->created_at?->format('Y-m-d'),
                $charge->student->full_name ?? '',
                $charge->concept,
                $charge->charge_type ?? '',
                $charge->period->code ?? ($charge->billing_period_label ?: ''),
                $charge->amount,
                $currency,
                $charge->due_date?->format('Y-m-d'),
                $charge->status,
                $paid,
                FinanceReconcile::outstandingForCharge($charge),
            ];
        })->all();

        return [
            'title' => 'Cargos creados',
            'filename' => 'cargos_creados',
            'headers' => ['Sede', 'Fecha creación', 'Alumno', 'Concepto', 'Tipo', 'Periodo', 'Monto', 'Moneda', 'Vencimiento', 'Estado', 'Cobrado', 'Saldo'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, filename: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function paymentsDataset(
        ?User $user,
        ?Carbon $startDate,
        ?Carbon $endDate,
        string $currency,
        ?int $campusId,
    ): array {
        $payments = FinanceSummary::paymentsQuery($user, $startDate, $endDate, $currency, $campusId)
            ->with(['student', 'campus', 'paymentMethod', 'receipt'])
            ->orderBy('paid_at')
            ->get();

        $rows = $payments->map(function (Payment $payment) use ($currency) {
            return [
                $payment->campus->name ?? '',
                $payment->paid_at?->format('Y-m-d'),
                $payment->student->full_name ?? '',
                $payment->currency ?? PaymentCurrencyConverter::CURRENCY_USD,
                $payment->original_amount ?? $payment->amount,
                $payment->exchange_rate,
                FinanceSummary::paymentAmountForCurrency($payment, $currency),
                $currency,
                $payment->method ?? $payment->paymentMethod?->label,
                $payment->reference,
                $payment->receipt?->receipt_number,
            ];
        })->all();

        return [
            'title' => 'Cobros realizados',
            'filename' => 'cobros_realizados',
            'headers' => ['Sede', 'Fecha', 'Alumno', 'Moneda pago', 'Monto original', 'Tasa', 'Monto reporte', 'Moneda reporte', 'Método', 'Referencia', 'Recibo'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{title: string, filename: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function projectionDataset(array $summary, string $currency, float $vesRate): array
    {
        /** @var Collection<int, array{label: string, amount: float}> $projection */
        $projection = $summary['projection'] ?? collect();

        $rows = $projection->map(function (array $row) use ($currency, $vesRate) {
            $vesEquivalent = $vesRate > 0
                ? ($currency === PaymentCurrencyConverter::CURRENCY_EUR
                    ? PaymentCurrencyConverter::eurVesEquivalent((float) $row['amount'], $vesRate)
                    : PaymentCurrencyConverter::vesEquivalent((float) $row['amount'], $vesRate))
                : 0;

            return [
                $row['label'],
                $row['amount'],
                $vesEquivalent,
                $currency,
            ];
        })->all();

        return [
            'title' => 'Proyección de cobros',
            'filename' => 'proyeccion_cobros',
            'headers' => ['Periodo', 'Monto', 'Equivalente Bs', 'Moneda'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{
     *     title: string,
     *     filename: string,
     *     filters: array<string, string|null>,
     *     summary: array<string, mixed>,
     *     generatedAt: Carbon
     * }
     */
    private function buildMeta(
        string $report,
        ?Carbon $startDate,
        ?Carbon $endDate,
        string $currency,
        ?int $campusId,
        array $summary,
    ): array {
        $campusName = $campusId
            ? (Campus::query()->find($campusId)?->name ?? 'Sede')
            : 'Todas las sedes';

        $titles = [
            'charges' => 'Cargos creados',
            'payments' => 'Cobros realizados',
            'projection' => 'Proyección de cobros',
        ];

        $filenames = [
            'charges' => 'cargos_creados',
            'payments' => 'cobros_realizados',
            'projection' => 'proyeccion_cobros',
        ];

        return [
            'title' => $titles[$report] ?? 'Reporte financiero',
            'filename' => $filenames[$report] ?? 'reporte_financiero',
            'filters' => [
                'start_date' => $startDate?->format('d/m/Y'),
                'end_date' => $endDate?->format('d/m/Y'),
                'currency' => $currency,
                'campus' => $campusName,
            ],
            'summary' => $summary,
            'generatedAt' => now(),
        ];
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<mixed>>, filename: string}  $dataset
     */
    private function csvResponse(array $dataset, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($dataset): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $dataset['headers']);
            foreach ($dataset['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<mixed>>, filename: string}  $dataset
     */
    private function xlsxResponse(array $dataset, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($dataset): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([$dataset['headers'], ...$dataset['rows']], null, 'A1', true);

            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{title: string, headers: list<string>, rows: list<list<mixed>>, filters: array<string, string|null>, summary: array<string, mixed>, generatedAt: Carbon}  $meta
     */
    private function pdfResponse(array $dataset, array $meta): Response
    {
        $pdf = Pdf::loadView('finance.summary-report-pdf', [
            'title' => $meta['title'],
            'headers' => $dataset['headers'],
            'rows' => $dataset['rows'],
            'filters' => $meta['filters'],
            'summary' => $meta['summary'],
            'generatedAt' => $meta['generatedAt'],
            'logoDataUri' => $this->buildLogoDataUri(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($meta['filename'].'.pdf');
    }

    private function buildLogoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
