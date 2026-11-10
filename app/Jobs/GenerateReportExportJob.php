<?php

namespace App\Jobs;

use App\Models\AttendanceRecord;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\ReportExport;
use App\Support\CampusScope;
use App\Support\CsvExport;
use App\Support\DateFormat;
use App\Support\FinanceReconcile;
use App\Support\PrivateFiles;
use App\Support\StatusLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reportExportId) {}

    public function handle(): void
    {
        $export = ReportExport::find($this->reportExportId);
        if (! $export || $export->status === 'done') {
            return;
        }

        $export->update(['status' => 'running', 'error_message' => null]);

        try {
            $filters = $export->filters ?? [];
            $filename = 'exports/report-'.$export->type.'-'.$export->id.'.csv';
            $handle = fopen('php://temp', 'w+');

            if ($export->type === 'attendance') {
                $query = AttendanceRecord::query()->with(['classSession.group', 'enrollment.student']);
                $query->whereHas('classSession', fn ($q) => $this->scopeCampus($q, $export));
                if (! empty($filters['from'])) {
                    $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '>=', $filters['from']));
                }
                if (! empty($filters['to'])) {
                    $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '<=', $filters['to']));
                }
                if (! empty($filters['group_id'])) {
                    $query->whereHas('classSession', fn ($q) => $q->where('group_id', (int) $filters['group_id']));
                }

                CsvExport::write($handle, ['Fecha', 'Grupo', 'Alumno', 'Cédula', 'Estado', 'Notas'], function () use ($query) {
                    foreach ($query->latest()->cursor() as $record) {
                        yield [
                            DateFormat::date($record->classSession->session_date, ''),
                            $record->classSession->group->name ?? '',
                            $record->enrollment->student->full_name ?? '',
                            $record->enrollment->student->document_id ?? '',
                            StatusLabel::label($record->status, 'attendance'),
                            $record->notes ?? '',
                        ];
                    }
                });
            } elseif ($export->type === 'payments') {
                $chargesQuery = Charge::query()->with(['student', 'payments']);
                $this->scopeCampus($chargesQuery, $export);
                if (! empty($filters['status'])) {
                    $chargesQuery->where('status', $filters['status']);
                }
                if (! empty($filters['only_overdue'])) {
                    $chargesQuery->where('status', 'overdue');
                }

                CsvExport::write($handle, ['Alumno', 'Cédula', 'Concepto', 'Moneda', 'Monto', 'Vence', 'Estado', 'Pagado', 'Saldo'], function () use ($chargesQuery) {
                    foreach ($chargesQuery->latest()->cursor() as $charge) {
                        yield [
                            $charge->student->full_name ?? '',
                            $charge->student->document_id ?? '',
                            $charge->concept,
                            $charge->currency ?? 'USD',
                            (float) $charge->amount,
                            DateFormat::date($charge->due_date, ''),
                            StatusLabel::label($charge->status),
                            (float) FinanceReconcile::paidTotalForCharge($charge),
                            (float) FinanceReconcile::outstandingForCharge($charge),
                        ];
                    }
                });

                CsvExport::row($handle, []);
                CsvExport::row($handle, ['Alumno', 'Fecha de pago', 'Moneda', 'Monto original', 'Tasa', 'Monto aplicado', 'Método', 'Referencia']);
                $paymentsQuery = Payment::query()->whereNull('voided_at')->with(['student'])->latest();
                $this->scopeCampus($paymentsQuery, $export);
                foreach ($paymentsQuery->cursor() as $payment) {
                    CsvExport::row($handle, [
                        $payment->student->full_name ?? '',
                        DateFormat::date($payment->paid_at, ''),
                        $payment->currency ?? 'USD',
                        (float) ($payment->original_amount ?? $payment->amount),
                        $payment->exchange_rate ? CsvExport::amount($payment->exchange_rate, 4) : '',
                        (float) $payment->amount,
                        $payment->method,
                        $payment->reference,
                    ]);
                }
            } else {
                throw new \RuntimeException('Tipo de exportación no soportado.');
            }

            rewind($handle);
            PrivateFiles::put($filename, stream_get_contents($handle));
            fclose($handle);

            $export->update([
                'status' => 'done',
                'file_path' => $filename,
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $export->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    /** Sede elegida en la exportación y, siempre, solo las sedes del usuario que la pidió. #439 */
    private function scopeCampus($query, ReportExport $export)
    {
        if ($export->campus_id) {
            $query->where('campus_id', $export->campus_id);
        }

        return CampusScope::apply($query, $export->user);
    }
}
