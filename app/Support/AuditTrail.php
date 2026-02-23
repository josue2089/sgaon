<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditTrail
{
    public static function log(Request $request, string $action, Model|null $auditable = null, array $payload = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->id,
            'payload' => $payload,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
