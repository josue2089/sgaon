<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'charge_id',
        'amount_applied',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'float',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }
}
