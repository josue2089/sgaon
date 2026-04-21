<?php

namespace Database\Seeders;

use App\Models\AcademicLevel;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Representative;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Campus;
use Illuminate\Database\Seeder;

class UatFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $campusA = Campus::create(['name' => 'Picacho', 'code' => 'PIC-UAT', 'status' => 'active']);
        $campusB = Campus::create(['name' => 'Cascada', 'code' => 'CAS-UAT', 'status' => 'active']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $roleTeacher = Role::firstOrCreate(['name' => 'teacher'], ['label' => 'Profesor']);
        $roleStudent = Role::firstOrCreate(['name' => 'student'], ['label' => 'Alumno']);
        $roleRep = Role::firstOrCreate(['name' => 'representative'], ['label' => 'Representante']);

        $adminA = User::factory()->create(['campus_id' => $campusA->id, 'role' => 'admin', 'email' => 'admina@uat.test']);
        $adminA->roles()->syncWithoutDetaching([$roleAdmin->id]);

        $teacherUser = User::factory()->create(['campus_id' => $campusA->id, 'role' => 'teacher', 'email' => 'teachera@uat.test']);
        $teacherUser->roles()->syncWithoutDetaching([$roleTeacher->id]);

        $studentUser = User::factory()->create(['campus_id' => $campusA->id, 'role' => 'student', 'email' => 'studenta@uat.test']);
        $studentUser->roles()->syncWithoutDetaching([$roleStudent->id]);

        $repUser = User::factory()->create(['campus_id' => $campusA->id, 'role' => 'representative', 'email' => 'repa@uat.test']);
        $repUser->roles()->syncWithoutDetaching([$roleRep->id]);

        $teacher = Teacher::create([
            'campus_id' => $campusA->id,
            'user_id' => $teacherUser->id,
            'first_name' => 'Teacher',
            'last_name' => 'A',
            'email' => $teacherUser->email,
            'status' => 'active',
        ]);

        $studentA = Student::create([
            'campus_id' => $campusA->id,
            'user_id' => $studentUser->id,
            'first_name' => 'Student',
            'last_name' => 'A',
            'email' => $studentUser->email,
            'status' => 'active',
        ]);

        $rep = Representative::create([
            'campus_id' => $campusA->id,
            'user_id' => $repUser->id,
            'first_name' => 'Rep',
            'last_name' => 'A',
            'email' => $repUser->email,
            'relation' => 'representante',
        ]);
        $studentA->representatives()->syncWithoutDetaching([$rep->id]);

        $studentB = Student::create([
            'campus_id' => $campusB->id,
            'first_name' => 'Student',
            'last_name' => 'B',
            'email' => 'studentb@uat.test',
            'status' => 'active',
        ]);

        $levelA = AcademicLevel::create(['campus_id' => $campusA->id, 'name' => 'Primary UAT']);
        $courseA = Course::create([
            'campus_id' => $campusA->id,
            'academic_level_id' => $levelA->id,
            'name' => 'BP1',
            'teacher_id' => $teacher->id,
            'status' => 'active',
        ]);
        $groupA = Group::create(['campus_id' => $campusA->id, 'course_id' => $courseA->id, 'teacher_id' => $teacher->id, 'name' => 'G-A', 'period' => '2026', 'status' => 'active']);
        $courseA->update(['managed_group_id' => $groupA->id]);

        Enrollment::create([
            'campus_id' => $campusA->id,
            'student_id' => $studentA->id,
            'group_id' => $groupA->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        $levelB = AcademicLevel::create(['campus_id' => $campusB->id, 'name' => 'Primary UAT B']);
        $courseB = Course::create(['campus_id' => $campusB->id, 'academic_level_id' => $levelB->id, 'name' => 'BP1-B', 'status' => 'active']);
        $groupB = Group::create(['campus_id' => $campusB->id, 'course_id' => $courseB->id, 'name' => 'G-B', 'period' => '2026', 'status' => 'active']);

        Enrollment::create([
            'campus_id' => $campusB->id,
            'student_id' => $studentB->id,
            'group_id' => $groupB->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);
    }
}
