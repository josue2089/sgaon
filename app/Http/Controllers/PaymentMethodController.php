<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $methods = PaymentMethod::query()
            ->orderBy('currency')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('settings.payment-methods.index', [
            'methods' => $methods,
        ]);
    }

    public function create(): View
    {
        return view('settings.payment-methods.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $method = PaymentMethod::create($data);

        AuditTrail::log($request, 'payment_method.create', $method, $data);

        return redirect()
            ->route('settings.payment-methods.index')
            ->with('success', 'Método de pago creado.');
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        return view('settings.payment-methods.form', $this->formData($paymentMethod));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $this->validatedData($request);
        $paymentMethod->update($data);

        AuditTrail::log($request, 'payment_method.update', $paymentMethod, $data);

        return redirect()
            ->route('settings.payment-methods.index')
            ->with('success', 'Método de pago actualizado.');
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        AuditTrail::log($request, 'payment_method.delete', $paymentMethod);

        return redirect()
            ->route('settings.payment-methods.index')
            ->with('success', 'Método de pago eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?PaymentMethod $paymentMethod = null): array
    {
        return [
            'paymentMethod' => $paymentMethod,
            'currencyLabels' => PaymentMethod::currencyLabels(),
            'typeLabels' => PaymentMethod::typeLabels(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'currency' => ['required', Rule::in([PaymentMethod::CURRENCY_USD, PaymentMethod::CURRENCY_VES])],
            'method_type' => ['required', Rule::in(array_keys(PaymentMethod::typeLabels()))],
            'label' => ['required', 'string', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_holder' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:80'],
            'rif' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'instructions' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($request->input('sort_order') ?? 0),
        ];
    }
}
