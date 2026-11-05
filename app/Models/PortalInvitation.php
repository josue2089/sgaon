<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PortalInvitation extends Model
{
    public const PURPOSE_INVITATION = 'invitation';

    public const PURPOSE_RECOVERY = 'recovery';

    protected $fillable = ['user_id', 'invited_by', 'purpose', 'token_hash', 'sent_to', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findUsable(string $token): ?self
    {
        return self::query()
            ->with('user')
            ->where('token_hash', self::hashToken($token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereHas('user', fn (Builder $query) => $query->where('status', '!=', 'inactive'))
            ->first();
    }
}
