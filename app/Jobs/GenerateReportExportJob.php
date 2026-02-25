<?php

namespace App\Jobs;

use App\Models\AttendanceRecord;
use App\Models\Charge;
use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reportExportId)
    {
    }

    public function handle(): void
    {
        $export = ReportExport::find($this->reportExportId);
        if (! $export || $export->status === 'done') {
            return;
        }

        $export->update(['status' => 'running', 'error_message' => null]);

        try {
            $filters = $export->filters ?? [];
            $filename = 'exports/report-'.$export->type.'-'.$export->id.'.csv';
            $handle = fopen('php://temp', 'w+');

            if ($export->type === 'attendance') {
                fputcsv($handle, ['date', 'group', 'student', 'status']);
                $query = AttendanceRecord::query()->with(['classSession.group', 'enrollment.student']);
                if ($export->campus_id) {
                    $query->whereHas('classSession', fn ($q) => $q->where('campus_id', $export->campus_id));
                }
                if (! empty($filters['from'])) {
                    $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '>=', $filters['from']));
                }
                if (! empty($filters['to'])) {
                    $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '<=', $filters['to']));
                }
                if (! empty($filters['group_id'])) {
                    $query->whereHas('classSession', fn ($q) => $q->where('group_id', (int) $filters['group_id']));
                }

                foreach ($query->latest()->cursor() as $record) {
                    fputcsv($handle, [
                        $record->classSession->session_date?->format('Y-m-d'),
                        $record->classSession->group->name ?? '',
                        $record->enrollment->student->full_name ?? '',
                        $record->status,
                    ]);
                }
            } elseif ($export->type === 'payments') {
                fputcsv($handle, ['student', 'concept', 'amount', 'status', 'paid', 'balance']);
                $query = Charge::query()->with(['student', 'payments']);
                if ($export->campus_id) {
                    $query->where('campus_id', $export->campus_id);
                }
                if (! empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }
                if (! empty($filters['only_overdue'])) {
                    $query->where('status', 'overdue');
                }

                foreach ($query->latest()->cursor() as $charge) {
                    $paid = (float) $charge->payments->sum('amount');
                    fputcsv($handle, [
                        $charge->student->full_name ?? '',
                        $charge->concept,
                        $charge->amount,
                        $charge->status,
                        $paid,
                        max(0, (float) $charge->amount - $paid),
                    ]);
                }
            } else {
                throw new \RuntimeException('Tipo de exportación no soportado.');
            }

            rewind($handle);
            Storage::disk('public')->put($filename, stream_get_contents($handle));
            fclose($handle);

            $export->update([
                'status' => 'done',
                'file_path' => $filename,
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $export->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
