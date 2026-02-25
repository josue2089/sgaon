<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportExportJob;
use App\Models\AuditLog;
use App\Models\AttendanceRecord;
use App\Models\Charge;
use App\Models\ReportExport;
use App\Models\ReportPreset;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function attendance(Request $request): View|StreamedResponse
    {
        $query = AttendanceRecord::query()->with(['classSession.group', 'enrollment.student']);
        if ($request->user()?->campus_id) {
            $query->whereHas('classSession', fn ($q) => $q->where('campus_id', $request->user()->campus_id));
        }

        if ($request->filled('from')) {
            $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '>=', $request->date('from')));
        }

        if ($request->filled('to')) {
            $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '<=', $request->date('to')));
        }

        if ($request->filled('group_id')) {
            $groupId = (int) $request->query('group_id');
            $query->whereHas('classSession', fn ($q) => $q->where('group_id', $groupId));
        }

        if ($request->query('export') === 'csv') {
            return $this->attendanceCsv($query->latest()->get());
        }

        return view('reports.attendance', [
            'records' => $query->latest()->paginate(40)->withQueryString(),
            'groups' => \App\Models\Group::query()
                ->when($request->user()?->campus_id, fn ($builder) => $builder->where('campus_id', $request->user()->campus_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'presets' => ReportPreset::query()
                ->where('user_id', $request->user()->id)
                ->where('route_name', 'reports.attendance')
                ->latest()
                ->get(),
            'exports' => ReportExport::query()
                ->where('user_id', $request->user()->id)
                ->where('type', 'attendance')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function payments(Request $request): View|StreamedResponse
    {
        $query = Charge::with(['student', 'payments'])->latest();
        if ($request->user()?->campus_id) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->boolean('only_overdue')) {
            $query->where('status', 'overdue');
        }

        if ($request->query('export') === 'csv') {
            return $this->paymentsCsv($query->get());
        }

        return view('reports.payments', [
            'charges' => $query->paginate(40)->withQueryString(),
            'presets' => ReportPreset::query()
                ->where('user_id', $request->user()->id)
                ->where('route_name', 'reports.payments')
                ->latest()
                ->get(),
            'exports' => ReportExport::query()
                ->where('user_id', $request->user()->id)
                ->where('type', 'payments')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function audit(): View
    {
        $query = AuditLog::with('user')->latest();
        if (request()->user()?->campus_id) {
            $query->whereHas('user', fn ($q) => $q->where('campus_id', request()->user()->campus_id));
        }

        return view('reports.audit', [
            'logs' => $query->paginate(50)->withQueryString(),
        ]);
    }

    public function storePreset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'route_name' => ['required', 'in:reports.attendance,reports.payments'],
            'name' => ['required', 'string', 'max:80'],
        ]);

        $filters = collect($request->query())
            ->except(['page', 'export'])
            ->toArray();

        ReportPreset::create([
            'user_id' => $request->user()->id,
            'route_name' => $data['route_name'],
            'name' => $data['name'],
            'filters' => $filters,
        ]);

        return back()->with('success', 'Preset guardado.');
    }

    public function destroyPreset(Request $request, ReportPreset $preset): RedirectResponse
    {
        if ((int) $preset->user_id !== (int) $request->user()->id) {
            abort(403);
        }
        $preset->delete();

        return back()->with('success', 'Preset eliminado.');
    }

    public function queueExport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:attendance,payments'],
        ]);

        $filters = collect($request->query())
            ->except(['page', 'export'])
            ->toArray();

        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'campus_id' => $request->user()->campus_id,
            'type' => $data['type'],
            'filters' => $filters,
            'status' => 'pending',
        ]);

        GenerateReportExportJob::dispatch($export->id);

        return back()->with('success', 'Exportación en cola. Actualiza en unos segundos para descargar.');
    }

    public function downloadExport(Request $request, ReportExport $export)
    {
        if ((int) $export->user_id !== (int) $request->user()->id) {
            abort(403);
        }
        if ($export->status !== 'done' || ! $export->file_path || ! Storage::disk('public')->exists($export->file_path)) {
            return back()->withErrors(['export' => 'El archivo aún no está disponible.']);
        }

        return Storage::disk('public')->download($export->file_path);
    }

    private function attendanceCsv($records): StreamedResponse
    {
        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'group', 'student', 'status']);
            foreach ($records as $record) {
                fputcsv($out, [
                    $record->classSession->session_date?->format('Y-m-d'),
                    $record->classSession->group->name ?? '',
                    $record->enrollment->student->full_name ?? '',
                    $record->status,
                ]);
            }
            fclose($out);
        }, 'attendance_report.csv', ['Content-Type' => 'text/csv']);
    }

    private function paymentsCsv($charges): StreamedResponse
    {
        return response()->streamDownload(function () use ($charges) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student', 'concept', 'amount', 'status', 'paid', 'balance']);
            foreach ($charges as $charge) {
                $paid = (float) $charge->payments->sum('amount');
                fputcsv($out, [
                    $charge->student->full_name ?? '',
                    $charge->concept,
                    $charge->amount,
                    $charge->status,
                    $paid,
                    max(0, (float) $charge->amount - $paid),
                ]);
            }
            fclose($out);
        }, 'payments_report.csv', ['Content-Type' => 'text/csv']);
    }
}
