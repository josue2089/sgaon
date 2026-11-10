<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency',
        'rate',
        'effective_at',
        'captured_at',
        'source',
        'note',
        'created_by',
    ];

    public const SOURCE_MANUAL = 'manual';

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Última tasa guardada de la moneda. */
    public static function latestFor(string $currency): ?self
    {
        return self::query()->where('currency', $currency)->orderByDesc('effective_at')->orderByDesc('id')->first();
    }

    /**
     * Desactualizada si su fecha de vigencia es anterior al último día hábil.
     * El BCV publica en días hábiles; el viernes suele publicar la del lunes.
     */
    public function isStale(): bool
    {
        return $this->effective_at === null || $this->effective_at->copy()->startOfDay()->lt(self::previousBusinessDay());
    }

    public static function previousBusinessDay(): Carbon
    {
        $day = today()->subDay();
        while ($day->isWeekend()) {
            $day->subDay();
        }

        return $day;
    }

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'effective_at' => 'datetime',
            'captured_at' => 'datetime',
        ];
    }
}
