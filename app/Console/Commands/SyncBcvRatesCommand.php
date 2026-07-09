<?php

namespace App\Console\Commands;

use App\Services\Bcv\ExchangeRateService;
use Illuminate\Console\Command;

class SyncBcvRatesCommand extends Command
{
    protected $signature = 'bcv:sync-rates';

    protected $description = 'Sincroniza las tasas BCV USD/VES y EUR/VES desde la API Aerious';

    public function handle(ExchangeRateService $exchangeRateService): int
    {
        try {
            $rates = $exchangeRateService->syncAllLatest();
            foreach ($rates as $currency => $rate) {
                $this->info(sprintf(
                    'Tasa BCV %s sincronizada: Bs %s (vigente %s)',
                    $currency,
                    number_format((float) $rate->rate, 4, '.', ''),
                    optional($rate->effective_at)->format('Y-m-d') ?? 'N/D'
                ));
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('No se pudo sincronizar la tasa BCV: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
