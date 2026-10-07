<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\MakeupRequest;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Console\Command;

class SyncMakeupRequestsWithCharges extends Command
{
    protected $signature = 'makeups:sync-with-charges {--dry-run : Solo lista los cambios, sin aplicarlos}';

    protected $description = 'Alinea las solicitudes de recuperativa con el estado de su cargo (sin enviar correos).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        MakeupRequest::query()
            ->with(['charge', 'student'])
            ->whereNotNull('charge_id')
            ->whereIn('status', [
                MakeupRequest::STATUS_PENDING_PAYMENT,
                MakeupRequest::STATUS_PENDING_VALIDATION,
                MakeupRequest::STATUS_APPROVED_FOR_BOOKING,
            ])
            ->orderBy('id')
            ->get()
            ->each(function (MakeupRequest $request) use ($dryRun, &$rows): void {
                $charge = $request->charge;
                if (! $charge instanceof Charge) {
                    return;
                }

                $target = $this->expectedStatus($request, $charge);
                if (! $target || $target === $request->status) {
                    return;
                }

                $rows[] = [$request->id, $request->student?->full_name, $request->status, $target, $charge->id, $charge->status];
                if (! $dryRun) {
                    MakeupRecoveryEngine::syncWithCharge($charge, notify: false);
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '').'Solicitudes a actualizar: '.count($rows));
        if ($rows !== []) {
            $this->table(['Solicitud', 'Alumno', 'Estado actual', 'Estado nuevo', 'Cargo', 'Estado cargo'], $rows);
        }

        return self::SUCCESS;
    }

    private function expectedStatus(MakeupRequest $request, Charge $charge): ?string
    {
        if ($charge->voided_at) {
            return MakeupRequest::STATUS_CANCELLED;
        }

        if ($charge->status === 'paid') {
            return in_array($request->status, [MakeupRequest::STATUS_PENDING_PAYMENT, MakeupRequest::STATUS_PENDING_VALIDATION], true)
                ? MakeupRequest::STATUS_APPROVED_FOR_BOOKING
                : null;
        }

        return $request->status === MakeupRequest::STATUS_APPROVED_FOR_BOOKING && ! $request->booking()->exists()
            ? MakeupRequest::STATUS_PENDING_PAYMENT
            : null;
    }
}
