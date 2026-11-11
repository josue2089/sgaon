<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
use App\Jobs\GenerateReportExportJob;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Period;
use App\Models\ReportExport;
use App\Models\ReportPreset;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\Teacher;
use App\Support\CampusScope;
use App\Support\CsvExport;
use App\Support\DateFormat;
use App\Support\FinanceReconcile;
use App\Support\PrivateFiles;
use App\Support\StatusLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ScopesCampusAccess;

    public function attendance(Request $request): View|StreamedResponse
    {
        $query = AttendanceRecord::query()->with(['classSession.group', 'enrollment.student']);
        if ($this->campusScoped()) {
            $query->whereHas('classSession', fn ($q) => $this->applyCampusScope($q));
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
            'groups' => Group::query()
                ->tap(fn ($builder) => $this->applyCampusScope($builder, 'campus_id'))
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
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'campus_id' => ['nullable', 'integer'],
        ]);
        $campusId = ! empty($filters['campus_id']) && $this->canAccessCampus((int) $filters['campus_id']) ? (int) $filters['campus_id'] : null;
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        $query = Charge::with(['student.representatives', 'payments', 'course', 'group', 'period'])->latest();
        if ($this->campusScoped()) {
            $this->applyCampusScope($query, 'campus_id');
        }
        $query->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->when($start, fn ($q) => $q->whereDate('due_date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('due_date', '<=', $end));

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->boolean('only_overdue')) {
            $query->where('status', 'overdue');
        }

        if ($request->filled('period_id')) {
            $query->where('period_id', (int) $request->query('period_id'));
        }

        if ($request->filled('charge_type')) {
            $query->where('charge_type', (string) $request->query('charge_type'));
        }

        // Pagos recibidos: mismo rango (por fecha de pago) y sede. #449
        $paymentsQuery = Payment::query()->whereNull('voided_at')->with(['student.representatives', 'paymentMethod', 'receipt'])
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->when($start, fn ($q) => $q->whereDate('paid_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('paid_at', '<=', $end));
        if ($this->campusScoped()) {
            $this->applyCampusScope($paymentsQuery, 'campus_id');
        }

        if ($request->query('export') === 'csv') {
            return $this->paymentsCsv($query->get());
        }

        if ($request->query('export') === 'payments_detail_csv') {
            return $this->paymentsDetailCsv((clone $paymentsQuery)->latest('paid_at')->latest('id'));
        }

        $totalsByCurrency = (clone $paymentsQuery)
            ->selectRaw("COALESCE(currency, 'USD') as currency_code, COUNT(*) as total_count, SUM(COALESCE(original_amount, amount)) as original_total, SUM(amount) as applied_total")
            ->groupByRaw("COALESCE(currency, 'USD')")
            ->orderByRaw("COALESCE(currency, 'USD')")
            ->get();

        return view('reports.payments', [
            'charges' => $query->paginate(40)->withQueryString(),
            'recentPayments' => (clone $paymentsQuery)->latest('paid_at')->latest('id')->paginate(50, ['*'], 'pagos_page')->withQueryString(),
            'paymentTotals' => $totalsByCurrency,
            'filters' => $filters + ['campus_id' => $campusId],
            'campuses' => $this->applyCampusScope(Campus::query()->orderBy('name'), 'id')->get(['id', 'name']),
            'periods' => Period::query()
                ->tap(fn ($builder) => $this->applyCampusScope($builder, 'campus_id'))
                ->orderBy('code')
                ->get(['id', 'code']),
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
        if ($this->campusScoped()) {
            $query->whereHas('user', fn ($q) => $this->applyCampusScope($q));
        }

        return view('reports.audit', [
            'logs' => $query->paginate(50)->withQueryString(),
        ]);
    }

    public function levelRenewals(Request $request): View
    {
        $query = Alert::query()
            ->with('student')
            ->where('type', 'level_renewal')
            ->latest();

        if ($this->campusScoped()) {
            $this->applyCampusScope($query, 'campus_id');
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $emailStatus = (string) $request->query('email_status', '');
        if ($emailStatus === 'sent') {
            $query->whereNotNull('emailed_at');
        } elseif ($emailStatus === 'pending') {
            $query->whereNull('emailed_at');
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        $alerts = $query->paginate(40)->withQueryString();

        $summaryQuery = Alert::query()->where('type', 'level_renewal');
        if ($this->campusScoped()) {
            $this->applyCampusScope($summaryQuery, 'campus_id');
        }

        return view('reports.level-renewals', [
            'alerts' => $alerts,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('emailed_at')->count(),
                'pending' => (clone $summaryQuery)->whereNull('emailed_at')->count(),
                'open' => (clone $summaryQuery)->where('status', 'open')->count(),
            ],
        ]);
    }

    /** Solvencia por sede y tipo de programa: alumnos al día y con deuda, con la lista y exportación. #514 */
    public function solvency(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
            'category' => ['nullable', 'in:regular,extracurricular'],
            'bucket' => ['nullable', 'in:owing,up_to_date,active,over_30'],
            'list_campus_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $campusId = ! empty($filters['campus_id']) && CampusScope::userCanAccessCampus($user, (int) $filters['campus_id']) ? (int) $filters['campus_id'] : null;
        $report = \App\Support\SolvencyReport::build($user, $campusId, $filters['month'] ?? null);

        $bucket = $filters['bucket'] ?? null;
        $category = $filters['category'] ?? null;
        $listCampusId = ! empty($filters['list_campus_id']) ? (int) $filters['list_campus_id'] : null;
        $list = $report['students']
            ->when($listCampusId, fn ($rows) => $rows->where('campus_id', $listCampusId))
            ->when($category, fn ($rows) => $rows->where('category', $category))
            ->when($bucket === 'owing', fn ($rows) => $rows->where('owes', true))
            ->when($bucket === 'over_30', fn ($rows) => $rows->where('owes', true)->where('max_days', '>', 30))
            ->when($bucket === 'up_to_date', fn ($rows) => $rows->where('active', true)->where('owes', false))
            ->when($bucket === 'active', fn ($rows) => $rows->where('active', true))
            ->when(! $bucket, fn ($rows) => $rows->where('owes', true))
            ->values();
        $categories = \App\Support\SolvencyReport::CATEGORIES;
        $stamp = now()->format('Y-m-d');

        if ($request->query('export') === 'summary_csv') {
            $lines = $report['rows']->map(fn ($row) => [
                $row['campus_name'], $categories[$row['category']], $row['active'], $row['up_to_date'], $row['owing'], $row['owing_inactive'], $row['over_30'],
                $row['amounts']['USD'] ?? 0.0, $row['amounts']['EUR'] ?? 0.0,
            ])->push([
                'Total', '', $report['totals']['active'], $report['totals']['up_to_date'], $report['totals']['owing'], $report['totals']['owing_inactive'], $report['totals']['over_30'],
                $report['totals']['amounts']['USD'] ?? 0.0, $report['totals']['amounts']['EUR'] ?? 0.0,
            ]);

            return \App\Support\CsvExport::download('solvencia-resumen-'.$stamp.'.csv',
                ['Sede', 'Tipo', 'Alumnos activos', 'Al día', 'Con deuda', 'Con deuda sin inscripción activa', 'Mora > 30 días', 'Deuda USD', 'Deuda EUR'],
                $lines->all());
        }

        if ($request->query('export') === 'list_csv') {
            return \App\Support\CsvExport::download('solvencia-alumnos-'.$stamp.'.csv',
                ['Sede', 'Tipo', 'Alumno', 'Cédula', 'Curso', 'Inscripción activa', 'Representante', 'Teléfono', 'Deuda USD', 'Deuda EUR', 'Días de mora', 'Cargos pendientes'],
                $list->map(fn ($row) => [
                    $row['campus_name'], $categories[$row['category']], $row['student']->full_name, $row['student']->document_id,
                    implode(', ', $row['courses']), $row['active'] ? 'Sí' : 'No', $row['representative']?->full_name, $row['phone'],
                    $row['debt_amounts']['USD'] ?? 0.0, $row['debt_amounts']['EUR'] ?? 0.0, $row['max_days'], implode(' | ', $row['charges']),
                ])->all());
        }

        return view('reports.solvency', [
            'report' => $report,
            'list' => $list,
            'categories' => $categories,
            'filters' => ['campus_id' => $campusId, 'month' => $filters['month'] ?? null, 'category' => $category, 'bucket' => $bucket ?? 'owing', 'list_campus_id' => $listCampusId],
            'campuses' => CampusScope::apply(Campus::query()->orderBy('name'), $user, 'id')->get(['id', 'name']),
        ]);
    }

    public function campusTotals(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        $allowedIds = CampusScope::allowedCampusIds($user);

        $campuses = Campus::query()
            ->when(is_array($allowedIds), fn ($q) => $q->whereIn('id', $allowedIds ?: [0]))
            ->orderBy('name')
            ->get();

        $ids = $campuses->pluck('id');

        $studentsByCampus = Student::query()
            ->whereIn('campus_id', $ids)
            ->selectRaw("campus_id, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count, COUNT(*) as total_count")
            ->groupBy('campus_id')
            ->get()
            ->keyBy('campus_id');

        $groupsByCampus = Group::query()
            ->whereIn('campus_id', $ids)
            ->selectRaw("campus_id, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count, COUNT(*) as total_count")
            ->groupBy('campus_id')
            ->get()
            ->keyBy('campus_id');

        $teachersByCampus = Teacher::query()
            ->whereIn('campus_id', $ids)
            ->selectRaw('campus_id, COUNT(*) as total_count')
            ->groupBy('campus_id')
            ->pluck('total_count', 'campus_id');

        $coursesByCampus = Course::query()
            ->whereIn('campus_id', $ids)
            ->selectRaw('campus_id, COUNT(*) as total_count')
            ->groupBy('campus_id')
            ->pluck('total_count', 'campus_id');

        $enrollmentsByCampus = Enrollment::query()
            ->whereIn('campus_id', $ids)
            ->where('status', 'active')
            ->selectRaw('campus_id, COUNT(*) as total_count')
            ->groupBy('campus_id')
            ->pluck('total_count', 'campus_id');

        $rows = $campuses->map(function ($campus) use ($studentsByCampus, $groupsByCampus, $teachersByCampus, $coursesByCampus, $enrollmentsByCampus) {
            $students = $studentsByCampus->get($campus->id);
            $groups = $groupsByCampus->get($campus->id);

            return [
                'campus' => $campus,
                'students_total' => (int) ($students->total_count ?? 0),
                'students_active' => (int) ($students->active_count ?? 0),
                'groups_total' => (int) ($groups->total_count ?? 0),
                'groups_active' => (int) ($groups->active_count ?? 0),
                'teachers_total' => (int) ($teachersByCampus->get($campus->id) ?? 0),
                'courses_total' => (int) ($coursesByCampus->get($campus->id) ?? 0),
                'enrollments_active' => (int) ($enrollmentsByCampus->get($campus->id) ?? 0),
            ];
        });

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($rows): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Sede', 'Código', 'Alumnos activos', 'Alumnos totales', 'Grupos activos', 'Grupos totales', 'Docentes', 'Cursos', 'Inscripciones activas']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['campus']->name,
                        $row['campus']->code,
                        $row['students_active'],
                        $row['students_total'],
                        $row['groups_active'],
                        $row['groups_total'],
                        $row['teachers_total'],
                        $row['courses_total'],
                        $row['enrollments_active'],
                    ]);
                }
                fclose($out);
            }, 'totales_por_sede_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('reports.campus-totals', ['rows' => $rows]);
    }

    public function campusProjection(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        $allowedIds = CampusScope::allowedCampusIds($user);

        $fee = (float) SystemSetting::getValue(
            SystemSettingController::BILLING_MONTHLY_FEE_KEY,
            SystemSettingController::BILLING_MONTHLY_FEE_DEFAULT,
        );

        $campuses = Campus::query()
            ->when(is_array($allowedIds), fn ($q) => $q->whereIn('id', $allowedIds ?: [0]))
            ->orderBy('name')
            ->get();

        $ids = $campuses->pluck('id');

        $stats = Enrollment::query()
            ->where('status', 'active')
            ->whereIn('campus_id', $ids)
            ->selectRaw('campus_id, COUNT(*) as active_enrollments, COUNT(DISTINCT student_id) as unique_students')
            ->groupBy('campus_id')
            ->get()
            ->keyBy('campus_id');

        $rows = $campuses->map(function ($campus) use ($stats, $fee) {
            $s = $stats->get($campus->id);
            $active = (int) ($s->active_enrollments ?? 0);
            $unique = (int) ($s->unique_students ?? 0);

            return [
                'campus' => $campus,
                'unique_students' => $unique,
                'active_enrollments' => $active,
                'monthly_projection' => $active * $fee,
                'annual_projection' => $active * $fee * 10,
            ];
        });

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($rows, $fee): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Sede', 'Código', 'Alumnos únicos activos', 'Inscripciones activas', 'Tarifa mensual (USD)', 'Proyección mensual (USD)', 'Proyección anual estimada (USD)']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['campus']->name,
                        $row['campus']->code,
                        $row['unique_students'],
                        $row['active_enrollments'],
                        number_format($fee, 2, '.', ''),
                        number_format($row['monthly_projection'], 2, '.', ''),
                        number_format($row['annual_projection'], 2, '.', ''),
                    ]);
                }
                fclose($out);
            }, 'proyeccion_por_sede_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('reports.campus-projection', [
            'rows' => $rows,
            'fee' => $fee,
            'totalEnrollments' => (int) $rows->sum('active_enrollments'),
            'totalStudents' => (int) $rows->sum('unique_students'),
            'totalMonthly' => (float) $rows->sum('monthly_projection'),
            'totalAnnual' => (float) $rows->sum('annual_projection'),
            'generatedAt' => now(),
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
            'campus_id' => $this->singleAllowedCampusId(),
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
        if ($export->status !== 'done' || ! PrivateFiles::exists($export->file_path)) {
            return back()->withErrors(['export' => 'El archivo aún no está disponible.']);
        }

        return PrivateFiles::download($export->file_path);
    }

    private function attendanceCsv($records): StreamedResponse
    {
        return CsvExport::download('asistencia-'.now()->format('Y-m-d').'.csv', ['Fecha', 'Grupo', 'Alumno', 'Cédula', 'Estado', 'Notas'], function () use ($records) {
            foreach ($records as $record) {
                yield [
                    DateFormat::date($record->classSession->session_date, ''),
                    $record->classSession->group->name ?? '',
                    $record->enrollment->student->full_name ?? '',
                    $record->enrollment->student->document_id ?? '',
                    StatusLabel::label($record->status, 'attendance'),
                    $record->notes ?? '',
                ];
            }
        });
    }

    private function paymentsDetailCsv($query): StreamedResponse
    {
        return CsvExport::download('pagos-'.now()->format('Y-m-d').'.csv', [
            'Fecha de pago', 'Recibo', 'Alumno', 'Cédula del alumno', 'Representante', 'Cédula del representante', 'Teléfono del representante',
            'Moneda', 'Monto original', 'Tasa', 'Monto aplicado', 'Método', 'Referencia',
        ], function () use ($query) {
            foreach ($query->cursor() as $payment) {
                $representative = $payment->student?->representatives->first();
                yield [
                    DateFormat::date($payment->paid_at, ''),
                    $payment->receipt?->receipt_number ?? '',
                    $payment->student->full_name ?? '',
                    $payment->student->document_id ?? '',
                    $representative?->full_name ?? '',
                    $representative?->document_id ?? '',
                    $representative ? ($representative->mobile_phone ?: $representative->phone) : '',
                    $payment->currency ?? 'USD',
                    CsvExport::amount($payment->original_amount ?? $payment->amount),
                    $payment->exchange_rate ? CsvExport::amount($payment->exchange_rate, 4) : '',
                    CsvExport::amount($payment->amount),
                    $payment->paymentMethod?->label ?? $payment->method ?? '',
                    $payment->reference ?? '',
                ];
            }
        });
    }

    private function paymentsCsv($charges): StreamedResponse
    {
        return CsvExport::download('cuentas-por-cobrar-'.now()->format('Y-m-d').'.csv', [
            'Alumno', 'Cédula del alumno', 'Representante', 'Teléfono del representante', 'Curso', 'Grupo', 'Período',
            'Tipo', 'Concepto', 'Moneda', 'Monto', 'Vence', 'Estado', 'Pagado', 'Saldo',
        ], function () use ($charges) {
            foreach ($charges as $charge) {
                $representative = $charge->student?->representatives->first();
                yield [
                    $charge->student->full_name ?? '',
                    $charge->student->document_id ?? '',
                    $representative?->full_name ?? '',
                    $representative ? ($representative->mobile_phone ?: $representative->phone) : '',
                    $charge->course->name ?? '',
                    $charge->group->name ?? '',
                    $charge->period->code ?? '',
                    StatusLabel::chargeType($charge->charge_type),
                    $charge->concept,
                    $charge->currency ?? 'USD',
                    CsvExport::amount($charge->amount),
                    DateFormat::date($charge->due_date, ''),
                    StatusLabel::label($charge->status),
                    CsvExport::amount(FinanceReconcile::paidTotalForCharge($charge)),
                    CsvExport::amount(FinanceReconcile::outstandingForCharge($charge)),
                ];
            }
        });
    }
}
