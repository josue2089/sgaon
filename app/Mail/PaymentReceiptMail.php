<?php

namespace App\Mail;

use App\Models\Payment;
use App\Services\ReceiptPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function envelope(): Envelope
    {
        $receiptNumber = $this->payment->receipt?->receipt_number ?? 'recibo';

        return new Envelope(subject: 'Recibo de pago '.$receiptNumber);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-receipt');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $receipt = $this->payment->receipt;
        if (! $receipt) {
            return [];
        }

        $pdfService = app(ReceiptPdfService::class);

        return [
            Attachment::fromData(
                fn () => $pdfService->renderBinary($receipt),
                $pdfService->filename($receipt),
            )->withMime('application/pdf'),
        ];
    }
}
