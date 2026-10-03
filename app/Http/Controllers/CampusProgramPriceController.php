<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\CampusProgramPrice;
use App\Support\AuditTrail;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampusProgramPriceController extends Controller
{
    public function store(Request $request, Campus $campus): RedirectResponse
    {
        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'currency' => ['required', Rule::in([PaymentCurrencyConverter::CURRENCY_USD, PaymentCurrencyConverter::CURRENCY_EUR])],
        ]);

        $price = CampusProgramPrice::query()->updateOrCreate(
            ['campus_id' => $campus->id, 'program_id' => $data['program_id']],
            ['amount' => $data['amount'], 'currency' => $data['currency']],
        );

        AuditTrail::log($request, 'campus.program_price.save', $price, $price->toArray());

        return redirect()->route('campuses.show', $campus)->with('success', 'Precio de la sede guardado.');
    }

    public function destroy(Request $request, Campus $campus, CampusProgramPrice $price): RedirectResponse
    {
        abort_unless((int) $price->campus_id === (int) $campus->id, 404);

        AuditTrail::log($request, 'campus.program_price.delete', $price, $price->toArray());
        $price->delete();

        return redirect()->route('campuses.show', $campus)->with('success', 'Precio de la sede eliminado. Se usará el precio del nivel o programa.');
    }
}
