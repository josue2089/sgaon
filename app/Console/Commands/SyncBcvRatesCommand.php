<?php

namespace App\Console\Commands;

use App\Services\Bcv\ExchangeRateService;
use Illuminate\Console\Command;

class SyncBcvRatesCommand extends Command
{
    protected $signature = 'bcv:sync-rates';

    protected $description = 'Sincroniza la tasa BCV USD/VES desde la API Aerious';

    public function handle(ExchangeRateService $exchangeRateService): int
    {
        try {
            $rate = $exchangeRateService->syncLatest();
            $this->info(sprintf(
                'Tasa BCV sincronizada: Bs %s (vigente %s)',
                number_format((float) $rate->rate, 4, '.', ''),
                optional($rate->effective_at)->format('Y-m-d') ?? 'N/D'
            ));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('No se pudo sincronizar la tasa BCV: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
