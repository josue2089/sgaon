<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function editMakeupPaymentInstructions(): View
    {
        return view('settings.makeup-payment-instructions', [
            'instructions' => SystemSetting::getValue('makeup_payment_instructions', ''),
        ]);
    }

    public function updateMakeupPaymentInstructions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instructions' => ['nullable', 'string', 'max:8000'],
        ]);

        SystemSetting::putValue('makeup_payment_instructions', $data['instructions'] ?? null);

        return back()->with('success', 'Instrucciones de pago actualizadas.');
    }
}
