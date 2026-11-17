<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** El motor de correo se cambia a Resend solo con el .env (MAIL_MAILER=resend, RESEND_API_KEY). */
class MailEngineTest extends TestCase
{
    public function test_resend_transport_is_available(): void
    {
        config(['mail.default' => 'resend', 'services.resend.key' => 're_test_dummy']);

        $transport = Mail::mailer('resend')->getSymfonyTransport();

        $this->assertSame('resend', (string) $transport);
    }

    public function test_send_test_command_requires_resend_key(): void
    {
        config(['mail.default' => 'resend', 'services.resend.key' => null]);

        $this->artisan('mail:send-test', ['to' => 'prueba@example.test'])
            ->expectsOutputToContain('Falta RESEND_API_KEY')
            ->assertFailed();
    }

    public function test_send_test_command_sends_with_configured_mailer(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('mail:send-test', ['to' => 'prueba@example.test'])
            ->expectsOutputToContain('Mailer: array')
            ->assertSuccessful();

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('prueba@example.test', $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }
}
