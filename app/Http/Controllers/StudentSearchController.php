<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\CampusScope;
use App\Support\StatusLabel;
use App\Support\StudentSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Búsqueda global de alumnos del header: nombre, cédula, email o representante, dentro de la sede del usuario. */
class StudentSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $students = StudentSearch::applyTerm(
            CampusScope::apply(Student::query()->with('campus:id,name'), $request->user()),
            $term
        )
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(8)
            ->get(['id', 'campus_id', 'first_name', 'last_name', 'document_id', 'status']);

        return response()->json([
            'results' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->full_name,
                'detail' => collect([
                    $student->document_id,
                    $student->campus?->name,
                    $student->status !== 'active' ? StatusLabel::label($student->status) : null,
                ])->filter()->implode(' · '),
                'url' => route('students.show', $student),
            ])->values(),
        ]);
    }
}
