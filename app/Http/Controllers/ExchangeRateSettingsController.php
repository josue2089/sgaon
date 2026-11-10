<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Services\Bcv\ExchangeRateService;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** "Tasas BCV": historial, sincronización manual y tasa manual con motivo. #443 */
class ExchangeRateSettingsController extends Controller
{
    public const CURRENCIES = ['USD', 'EUR'];

    public function index(Request $request): View
    {
        $currency = in_array($request->query('currency'), self::CURRENCIES, true) ? $request->query('currency') : null;

        return view('settings.exchange-rates', [
            'current' => collect(self::CURRENCIES)->mapWithKeys(fn ($code) => [$code => ExchangeRate::latestFor($code)])->all(),
            'previousBusinessDay' => ExchangeRate::previousBusinessDay(),
            'rates' => ExchangeRate::query()
                ->with('author:id,name')
                ->when($currency, fn ($query) => $query->where('currency', $currency))
                ->orderByDesc('effective_at')->orderByDesc('id')
                ->paginate(30)->withQueryString(),
            'currency' => $currency,
            'canSetManual' => $request->user()->isMasterAdmin(),
        ]);
    }

    public function sync(Request $request, ExchangeRateService $rates): RedirectResponse
    {
        try {
            $synced = $rates->syncAllLatest();
        } catch (\Throwable $exception) {
            Log::warning('BCV sync from settings failed', ['message' => $exception->getMessage()]);
            AuditTrail::log($request, 'exchange_rates.sync_failed', null, ['message' => mb_substr($exception->getMessage(), 0, 200)]);

            return back()->with('error', 'El BCV no respondió. Intenta de nuevo en unos minutos o carga la tasa a mano.');
        }
        $rates->forgetCachedLatest();
        AuditTrail::log($request, 'exchange_rates.synced', null, collect($synced)->map(fn (ExchangeRate $rate) => (float) $rate->rate)->all());

        return back()->with('success', collect($synced)->map(fn (ExchangeRate $rate, $code) => $code.' Bs '.number_format((float) $rate->rate, 4, ',', '.'))->implode(' · ').' (vigente '.$synced['USD']->effective_at?->format('d/m/Y').').');
    }

    public function storeManual(Request $request, ExchangeRateService $rates): RedirectResponse
    {
        abort_unless($request->user()->isMasterAdmin(), 403);
        $data = $request->validate([
            'currency' => ['required', 'in:'.implode(',', self::CURRENCIES)],
            'rate' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'effective_at' => ['required', 'date', 'before_or_equal:'.today()->addDays(7)->toDateString()],
            'note' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['rate' => 'tasa', 'effective_at' => 'fecha de vigencia', 'note' => 'motivo', 'currency' => 'moneda']);

        $record = $rates->storeManual($data['currency'], (float) $data['rate'], Carbon::parse($data['effective_at'])->startOfDay(), trim($data['note']), $request->user()->id);
        AuditTrail::log($request, 'exchange_rates.manual', $record, ['currency' => $record->currency, 'rate' => (float) $record->rate, 'effective_at' => $record->effective_at->toDateString(), 'note' => $record->note]);

        return back()->with('success', 'Tasa manual de '.$record->currency.' guardada para el '.$record->effective_at->format('d/m/Y').'.');
    }
}
