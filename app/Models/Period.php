<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'code',
        'description',
        'status',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Ids de los períodos con el mismo código que el dado (cada sede tiene el suyo, p. ej. 2026-3). #450
     *
     * @return list<int>
     */
    public static function sameCodeIds(int $periodId): array
    {
        $code = static::query()->whereKey($periodId)->value('code');

        return $code === null ? [0] : static::query()->where('code', $code)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
