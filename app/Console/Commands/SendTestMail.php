<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Comprueba el motor de correo: muestra el mailer y el remitente configurados (sin claves) y envía un correo de prueba
 * en el momento, sin pasar por la cola. Se usa al cambiar de proveedor (Mailgun → Resend).
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:send-test {to : Correo de destino}';

    protected $description = 'Envía un correo de prueba con el mailer configurado y muestra qué motor y remitente usa.';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $this->line("Mailer: {$mailer} · Remitente: {$from}");

        if ($mailer === 'resend' && blank(config('services.resend.key'))) {
            $this->error('Falta RESEND_API_KEY en el .env.');

            return self::FAILURE;
        }

        try {
            Mail::raw('Correo de prueba de Sgaon enviado el '.now()->format('d/m/Y H:i').' con el motor "'.$mailer.'".', function ($message) {
                $message->to((string) $this->argument('to'))->subject('Prueba de correo · Sgaon');
            });
        } catch (Throwable $exception) {
            $this->error('No se pudo enviar: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Enviado.');

        return self::SUCCESS;
    }
}
