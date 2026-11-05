<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Correo que sale por la cola, después del commit: un fallo de Mailgun o del PDF no rompe ni deshace
 * lo que el usuario guardó, y el envío se reintenta. Si se agotan los intentos queda en failed_jobs y en el log. #440
 */
abstract class QueuedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    /** Segundos antes de cada reintento. */
    public $backoff = [60, 300];

    /** Menor que retry_after de la cola (90 s), para que el recibo en PDF no se envíe dos veces. */
    public $timeout = 60;

    public function queue(Queue $queue)
    {
        $this->afterCommit();

        return parent::queue($queue);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('No se pudo enviar el correo '.static::class, [
            'to' => collect($this->to)->pluck('address')->all(),
            'error' => $exception->getMessage(),
        ]);
    }
}
