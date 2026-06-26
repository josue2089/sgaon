<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    public const CURRENCY_USD = 'USD';

    public const CURRENCY_VES = 'VES';

    public const TYPE_ZELLE = 'zelle';

    public const TYPE_PAGO_MOVIL = 'pago_movil';

    public const TYPE_TRANSFERENCIA = 'transferencia';

    public const TYPE_OTRO = 'otro';

    protected $fillable = [
        'currency',
        'method_type',
        'label',
        'bank_name',
        'account_holder',
        'account_number',
        'rif',
        'phone',
        'email',
        'instructions',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_ZELLE => 'Zelle',
            self::TYPE_PAGO_MOVIL => 'Pago móvil',
            self::TYPE_TRANSFERENCIA => 'Transferencia',
            self::TYPE_OTRO => 'Otro',
        ];
    }

    public static function currencyLabels(): array
    {
        return [
            self::CURRENCY_USD => 'USD (dólares)',
            self::CURRENCY_VES => 'Bs (bolívares)',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function chargePaymentRequests(): HasMany
    {
        return $this->hasMany(ChargePaymentRequest::class);
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->method_type] ?? $this->method_type;
    }

    public function accountSummaryLines(): array
    {
        $lines = [];

        if ($this->bank_name) {
            $lines[] = 'Banco: '.$this->bank_name;
        }
        if ($this->account_holder) {
            $lines[] = 'Titular: '.$this->account_holder;
        }
        if ($this->account_number) {
            $lines[] = 'Cuenta: '.$this->account_number;
        }
        if ($this->rif) {
            $lines[] = 'RIF: '.$this->rif;
        }
        if ($this->phone) {
            $lines[] = 'Teléfono: '.$this->phone;
        }
        if ($this->email) {
            $lines[] = 'Email: '.$this->email;
        }
        if ($this->instructions) {
            $lines[] = $this->instructions;
        }

        return $lines;
    }
}
