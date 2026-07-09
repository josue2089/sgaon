<?php

namespace App\Services;

use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentReceiptNotifier
{
    public function notify(Payment $payment): void
    {
        $payment->loadMissing(['receipt', 'student.representatives', 'allocations.charge', 'paymentMethod']);

        if (! $payment->receipt) {
            Log::info('Payment receipt email skipped: payment has no receipt', ['payment_id' => $payment->id]);

            return;
        }

        $recipients = $this->recipientsForStudent($payment->student);
        if ($recipients->isEmpty()) {
            Log::info('Payment receipt email skipped: no recipients', ['payment_id' => $payment->id]);

            return;
        }

        Mail::to($recipients->all())->send(new PaymentReceiptMail($payment));
    }

    private function recipientsForStudent(?Student $student): Collection
    {
        if (! $student) {
            return collect();
        }

        $student->loadMissing('representatives');

        return collect([
            $student->email,
            ...$student->representatives->pluck('email')->all(),
        ])->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();
    }
}
