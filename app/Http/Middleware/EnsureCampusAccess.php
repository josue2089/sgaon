<?php

namespace App\Http\Middleware;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
use App\Models\Payment;
use App\Support\CampusScope;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampusAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Un usuario desactivado pierde la sesión que tenía abierta. #471
        if ($user->status === 'inactive') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Este usuario está desactivado. Consulta con la administración.']);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $recordCampusId = $this->resolveCampusId($parameter);

            if ($recordCampusId !== null && ! CampusScope::userCanAccessCampus($user, $recordCampusId)) {
                abort(403);
            }
        }

        return $next($request);
    }

    private function resolveCampusId(Model $model): ?int
    {
        if (isset($model->campus_id)) {
            return (int) $model->campus_id;
        }

        if ($model instanceof Enrollment) {
            return (int) $model->campus_id;
        }

        if ($model instanceof ClassSession) {
            return (int) $model->campus_id;
        }

        if ($model instanceof Payment) {
            return (int) $model->campus_id;
        }

        if ($model instanceof GradeEvaluationSet || $model instanceof GradeEntry) {
            return (int) $model->campus_id;
        }

        return null;
    }
}
