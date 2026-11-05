<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
use App\Models\Course;
use App\Models\Program;
use App\Models\Representative;
use App\Models\Student;
use App\Services\PortalAccessService;
use App\Support\AuditTrail;
use App\Support\PortalAccessDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Pantalla "Accesos al portal": invitar en lote a las familias de las sedes del admin. #472 */
class PortalAccessController extends Controller
{
    use ScopesCampusAccess;

    public function index(Request $request, PortalAccessService $access, PortalAccessDirectory $directory): View
    {
        $filters = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'state' => ['nullable', 'string', 'max:20'],
        ]);

        $query = $this->applyCampusScope($directory->students($filters));
        $people = collect($access->peopleFor($query->get()))
            ->reject(fn (array $row) => $row['blocker'] === PortalAccessService::BLOCKER_KIDS)
            ->map(fn (array $row) => $row + ['selectable' => $access->canInviteInBulk($row)])
            ->sortBy('name')
            ->values();

        $summary = [
            'invitable' => $people->where('selectable', true)->count(),
            'active' => $people->where('state', PortalAccessService::STATE_ACTIVE)->count(),
            'blocked' => $people->where('can_invite', false)->groupBy('blocker')->map->count()->all(),
        ];

        if (! empty($filters['state'])) {
            $people = $people->where('state', $filters['state'])->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $people->forPage($page, 50)->values(),
            $people->count(),
            50,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('portal_access.index', [
            'people' => $paginator,
            'summary' => $summary,
            'filters' => $filters,
            'campuses' => $this->allowedCampusesQuery()->get(['id', 'name']),
            'programs' => Program::query()->where('status', 'active')->where('is_extracurricular', false)->orderBy('name')->get(['id', 'name']),
            'courses' => $this->applyCampusScope(Course::query()->where('status', 'active'))->orderBy('name')->get(['id', 'name']),
            'states' => [
                PortalAccessService::STATE_NONE => 'Sin acceso',
                PortalAccessService::STATE_INVITED => 'Invitado',
                PortalAccessService::STATE_EXPIRED => 'Invitación vencida',
                PortalAccessService::STATE_ACTIVE => 'Activo',
                PortalAccessService::STATE_DISABLED => 'Desactivado',
            ],
        ]);
    }

    public function invite(Request $request, PortalAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'people' => ['required', 'array', 'min:1', 'max:200'],
            'people.*' => ['required', 'string', 'regex:/^(student|representative):\d+$/'],
        ], ['people.required' => 'Selecciona al menos una persona.']);

        $sent = 0;
        $skipped = [];
        foreach (array_unique($data['people']) as $key) {
            [$kind, $id] = explode(':', $key);
            $profile = $this->allowedProfile($kind, (int) $id);
            abort_unless($profile, 403);

            try {
                $invitation = $kind === 'student'
                    ? $access->inviteStudent($profile, $request->user())
                    : $access->inviteRepresentative($profile, $request->user());
                $sent++;
                AuditTrail::log($request, 'portal.access.invite', $invitation->user, ['kind' => $kind, 'sent_to' => $invitation->sent_to, 'via' => 'lote']);
            } catch (ValidationException $exception) {
                $skipped[] = $profile->full_name.': '.collect($exception->errors())->flatten()->first();
            }
        }

        $redirect = back()->with('success', "Invitaciones enviadas: {$sent}.");

        return $skipped === [] ? $redirect : $redirect->with('warning', 'No se pudo invitar a '.implode(' · ', $skipped));
    }

    /** La persona debe tener relación con una sede del admin. */
    private function allowedProfile(string $kind, int $id): Student|Representative|null
    {
        if ($kind === 'student') {
            return $this->applyCampusScope(Student::query())->find($id);
        }

        return Representative::query()
            ->whereKey($id)
            ->whereHas('students', fn ($students) => $this->applyCampusScope($students, 'students.campus_id'))
            ->first();
    }
}
