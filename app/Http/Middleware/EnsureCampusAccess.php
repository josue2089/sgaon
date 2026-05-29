<?php

namespace App\Http\Middleware;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
use App\Models\Payment;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampusAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isMasterAdmin()) {
            return $next($request);
        }

        $campusId = $user->campus_id;

        if (! $campusId) {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $recordCampusId = $this->resolveCampusId($parameter);

            if ($recordCampusId !== null && (int) $recordCampusId !== (int) $campusId) {
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
