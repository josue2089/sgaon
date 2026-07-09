<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Support\StudentSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_document_id_and_representative_name(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id]);

        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'document_id' => 'V-12345678',
            'email' => 'ana@student.test',
            'status' => 'active',
        ]);

        $representative = Representative::create([
            'campus_id' => $campus->id,
            'first_name' => 'María',
            'last_name' => 'González',
            'email' => 'maria@rep.test',
        ]);
        $student->representatives()->attach($representative->id);

        $byDocument = Student::query()->where('campus_id', $campus->id);
        StudentSearch::applyTerm($byDocument, '12345678');
        $this->assertTrue($byDocument->where('id', $student->id)->exists());

        $byRepresentative = Student::query()->where('campus_id', $campus->id);
        StudentSearch::applyTerm($byRepresentative, 'González');
        $this->assertTrue($byRepresentative->where('id', $student->id)->exists());

        $student->load('representatives');
        $this->assertStringContainsString('v-12345678', StudentSearch::haystack($student));
        $this->assertStringContainsString('maría gonzález', StudentSearch::haystack($student));

        $this->actingAs($admin)
            ->get(route('students.index', ['q' => '12345678']))
            ->assertOk()
            ->assertSee('Ana Pérez');

        $this->actingAs($admin)
            ->get(route('students.index', ['q' => 'González']))
            ->assertOk()
            ->assertSee('Ana Pérez');
    }
}
