<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use App\Models\Group;
use App\Support\CoursePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursePlannerManagedGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_persists_managed_group_id_without_schedule_template(): void
    {
        $campus = Campus::query()->create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $level = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'Primary']);
        $course = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $level->id,
            'name' => 'Curso demo',
            'status' => 'active',
        ]);

        CoursePlanner::sync($course, regenerateSessions: false);

        $course->refresh();
        $this->assertNotNull($course->managed_group_id, 'managed_group_id no fue persistido en el Course.');

        $groups = Group::query()->where('course_id', $course->id)->get();
        $this->assertCount(1, $groups, 'CoursePlanner debería crear un solo Group asociado al curso.');
        $this->assertSame($course->managed_group_id, $groups->first()->id);
    }

    public function test_sync_reuses_existing_group_instead_of_creating_a_duplicate(): void
    {
        $campus = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $level = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'HS']);
        $course = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $level->id,
            'name' => 'Curso preexistente',
            'status' => 'active',
        ]);

        $orphan = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $course->id,
            'name' => 'Grupo huérfano',
            'status' => 'active',
            'capacity' => 30,
        ]);

        CoursePlanner::sync($course, regenerateSessions: false);
        CoursePlanner::sync($course, regenerateSessions: false);

        $course->refresh();
        $this->assertSame($orphan->id, $course->managed_group_id);
        $this->assertSame(1, Group::query()->where('course_id', $course->id)->count());
    }
}
