<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MakeupBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'makeup_request_id',
        'makeup_session_id',
        'booked_at',
        'attended_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    public function makeupRequest()
    {
        return $this->belongsTo(MakeupRequest::class);
    }

    public function makeupSession()
    {
        return $this->belongsTo(MakeupSession::class);
    }
}
