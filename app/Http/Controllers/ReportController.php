<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AttendanceRecord;
use App\Models\Charge;
use Illuminate\Http\Request;
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

        if ($request->query('export') === 'csv') {
            return $this->attendanceCsv($query->latest()->get());
        }

        return view('reports.attendance', ['records' => $query->latest()->paginate(40)->withQueryString()]);
    }

    public function payments(Request $request): View|StreamedResponse
    {
        $query = Charge::with(['student', 'payments'])->latest();
        if ($request->user()?->campus_id) {
            $query->where('campus_id', $request->user()->campus_id);
        }

        if ($request->query('export') === 'csv') {
            return $this->paymentsCsv($query->get());
        }

        return view('reports.payments', ['charges' => $query->paginate(40)->withQueryString()]);
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
            fputcsv($out, ['student', 'concept', 'amount', 'status', 'paid']);
            foreach ($charges as $charge) {
                fputcsv($out, [
                    $charge->student->full_name ?? '',
                    $charge->concept,
                    $charge->amount,
                    $charge->status,
                    (float) $charge->payments->sum('amount'),
                ]);
            }
            fclose($out);
        }, 'payments_report.csv', ['Content-Type' => 'text/csv']);
    }
}
