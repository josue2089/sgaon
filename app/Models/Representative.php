<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Representative extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'user_id',
        'first_name',
        'last_name',
        'document_id',
        'nationality',
        'email',
        'phone',
        'address',
        'home_phone',
        'mobile_phone',
        'relation',
        'work_place',
        'work_address',
        'office_phone',
    ];

    /**
     * Fichas de representante de un usuario: las vinculadas o, si no hay, las de su correo sin vincular.
     * Puede ser más de una (misma cuenta con correo compartido): el portal muestra a todos sus hijos. #472
     *
     * @return Collection<int, self>
     */
    public static function forUser(User $user): Collection
    {
        $linked = self::query()->where('user_id', $user->id)->get();
        if ($linked->isNotEmpty() || ! $user->email) {
            return $linked;
        }

        return self::query()->whereNull('user_id')->where('email', $user->email)->get();
    }

    /** Busca la ficha con esa cédula, sin importar cómo se escribió (V-12.345.678, 12345678…). */
    public static function withDocument(?string $document): Collection
    {
        $number = DocumentNumber::normalize($document);
        if (! $number) {
            return new Collection;
        }

        return self::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(document_id), '.', ''), '-', ''), ' ', '') IN (?, ?, ?)", [$number, 'V'.$number, 'E'.$number])
            ->orderBy('id')
            ->get();
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_representative');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chargePaymentRequests()
    {
        return $this->hasMany(ChargePaymentRequest::class)->latest();
    }
}
