<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\AuditTrail;
use App\Support\DataHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/** "Salud de datos" (solo master): las verificaciones que antes eran comandos. #445 */
class DataHealthController extends Controller
{
    public function index(): View
    {
        return view('data_health.index', [
            'summary' => DataHealth::summary(),
            'chargeStatuses' => DataHealth::chargeStatuses(),
            'staleCharges' => DataHealth::staleCharges(),
            'duplicateGroups' => DataHealth::duplicateGroups(),
            'teachersWithoutAccess' => DataHealth::teachersWithoutAccess(),
            'representativesToMerge' => DataHealth::representativesToMerge(),
            'representativesToReview' => DataHealth::representativesToReview(),
            'lastReconcile' => AuditLog::query()->where('action', 'data_health.reconcile_charges')->latest('id')->first(),
        ]);
    }

    public function reconcileCharges(Request $request): RedirectResponse
    {
        Artisan::call('finance:reconcile-charges');
        $output = trim(Artisan::output());
        AuditTrail::log($request, 'data_health.reconcile_charges', null, ['output' => $output]);

        return back()->with('success', $output ?: 'Estados de cargos recalculados.');
    }
}
