<?php

namespace App\Services;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class ReceiptPdfService
{
    public function allocationsFor(Receipt $receipt): Collection
    {
        $receipt->loadMissing([
            'payment.allocations.charge.course',
            'payment.allocations.charge.group',
            'payment.allocations.charge.period',
            'payment.charge.course',
            'payment.charge.group',
            'payment.charge.period',
        ]);

        $payment = $receipt->payment;
        $allocations = $payment->allocations;

        if ($allocations->isEmpty() && $payment->charge) {
            return collect([
                (object) [
                    'amount_applied' => $payment->amount,
                    'charge' => $payment->charge,
                ],
            ]);
        }

        return $allocations;
    }

    public function renderBinary(Receipt $receipt): string
    {
        $receipt->loadMissing([
            'payment.student',
            'payment.receivedBy',
        ]);

        $allocations = $this->allocationsFor($receipt);

        return Pdf::loadView('finance.receipt-pdf', [
            'receipt' => $receipt,
            'payment' => $receipt->payment,
            'allocations' => $allocations,
            'logoDataUri' => $this->buildLogoDataUri(),
        ])->output();
    }

    public function filename(Receipt $receipt): string
    {
        return 'recibo-'.$receipt->receipt_number.'.pdf';
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
