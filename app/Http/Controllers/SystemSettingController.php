<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\AuditTrail;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public const BILLING_MONTHLY_FEE_KEY = 'billing.monthly_fee_usd';
    public const BILLING_MONTHLY_FEE_DEFAULT = '80';

    public function editMakeupPaymentInstructions(): View
    {
        return view('settings.makeup-payment-instructions', [
            'instructions' => SystemSetting::getValue('makeup_payment_instructions', ''),
            'dueDays' => MakeupRecoveryEngine::chargeDueDays(),
        ]);
    }

    public function updateMakeupPaymentInstructions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instructions' => ['nullable', 'string', 'max:8000'],
            'due_days' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        SystemSetting::putValue('makeup_payment_instructions', $data['instructions'] ?? null);
        SystemSetting::putValue(MakeupRecoveryEngine::CHARGE_DUE_DAYS_KEY, (string) $data['due_days']);

        return back()->with('success', 'Instrucciones de pago actualizadas.');
    }

    public function editBilling(): View
    {
        return view('settings.billing', [
            'fee' => (float) SystemSetting::getValue(self::BILLING_MONTHLY_FEE_KEY, self::BILLING_MONTHLY_FEE_DEFAULT),
        ]);
    }

    public function updateBilling(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fee_usd' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        SystemSetting::putValue(self::BILLING_MONTHLY_FEE_KEY, (string) $data['fee_usd']);
        AuditTrail::log($request, 'settings.billing.update', null, $data);

        return back()->with('success', 'Tarifa mensual actualizada.');
    }
}
