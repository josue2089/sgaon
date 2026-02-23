<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = ['campus_id', 'payment_id', 'receipt_number', 'issued_at'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
