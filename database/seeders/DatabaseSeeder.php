<?php

namespace Database\Seeders;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Permission;
use App\Models\Representative;
use App\Models\Role;
use App\Models\ScheduleTemplate;
use App\Models\Period;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $campus = Campus::firstOrCreate(
            ['code' => 'PICACHO'],
            ['name' => 'Sede Picacho', 'city' => 'San Antonio de los Altos', 'country' => 'Venezuela', 'status' => 'active'],
        );

        foreach (['Pre-Primary', 'Primary', 'High School'] as $index => $level) {
            AcademicLevel::firstOrCreate(
                ['campus_id' => $campus->id, 'name' => $level],
                ['code' => strtoupper(str_replace(' ', '_', $level)), 'sort_order' => $index],
            );
        }

        $roleAdmin = Role::firstOrCreate(['name' => User::ROLE_ADMIN], ['label' => 'Administrador']);
        $roleTeacher = Role::firstOrCreate(['name' => User::ROLE_TEACHER], ['label' => 'Profesor']);
        $roleStudent = Role::firstOrCreate(['name' => User::ROLE_STUDENT], ['label' => 'Alumno']);
        $roleRepresentative = Role::firstOrCreate(['name' => User::ROLE_REPRESENTATIVE], ['label' => 'Representante']);

        $permissionNames = [
            'dashboard.view',
            'students.manage',
            'teachers.manage',
            'courses.manage',
            'groups.manage',
            'enrollments.manage',
            'attendance.manage',
            'finance.manage',
            'reports.view',
            'portal.student.view',
            'portal.representative.view',
            'audit.view',
        ];

        $permissions = collect($permissionNames)->mapWithKeys(fn ($name) => [
            $name => Permission::firstOrCreate(['name' => $name], ['label' => $name]),
        ]);

        $roleAdmin->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $roleTeacher->permissions()->syncWithoutDetaching([
            $permissions['dashboard.view']->id,
            $permissions['attendance.manage']->id,
            $permissions['reports.view']->id,
        ]);
        $roleStudent->permissions()->syncWithoutDetaching([
            $permissions['dashboard.view']->id,
            $permissions['portal.student.view']->id,
        ]);
        $roleRepresentative->permissions()->syncWithoutDetaching([
            $permissions['dashboard.view']->id,
            $permissions['portal.representative.view']->id,
        ]);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@onenglish.test'],
            [
                'campus_id' => $campus->id,
                'name' => 'Administrador MVP',
                'phone' => '0000000000',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
            ],
        );
        $adminUser->forceFill(['is_master' => true])->save();

        $teacherUser = User::firstOrCreate(
            ['email' => 'teacher@onenglish.test'],
            [
                'campus_id' => $campus->id,
                'name' => 'Teacher Demo',
                'phone' => '0000000001',
                'password' => 'password',
                'role' => User::ROLE_TEACHER,
                'status' => 'active',
            ],
        );

        $studentUser = User::firstOrCreate(
            ['email' => 'student@onenglish.test'],
            [
                'campus_id' => $campus->id,
                'name' => 'Student Demo',
                'phone' => '0000000002',
                'password' => 'password',
                'role' => User::ROLE_STUDENT,
                'status' => 'active',
            ],
        );

        $repUser = User::firstOrCreate(
            ['email' => 'representative@onenglish.test'],
            [
                'campus_id' => $campus->id,
                'name' => 'Representative Demo',
                'phone' => '0000000003',
                'password' => 'password',
                'role' => User::ROLE_REPRESENTATIVE,
                'status' => 'active',
            ],
        );

        Teacher::firstOrCreate(
            ['campus_id' => $campus->id, 'user_id' => $teacherUser->id],
            [
                'first_name' => 'Teacher',
                'last_name' => 'Demo',
                'email' => $teacherUser->email,
                'phone' => $teacherUser->phone,
                'status' => 'active',
            ],
        );

        $student = Student::firstOrCreate(
            ['campus_id' => $campus->id, 'user_id' => $studentUser->id],
            [
                'first_name' => 'Student',
                'last_name' => 'Demo',
                'email' => $studentUser->email,
                'phone' => $studentUser->phone,
                'status' => 'active',
            ],
        );

        $representative = Representative::firstOrCreate(
            ['campus_id' => $campus->id, 'user_id' => $repUser->id],
            [
                'first_name' => 'Representative',
                'last_name' => 'Demo',
                'email' => $repUser->email,
                'phone' => $repUser->phone,
                'relation' => 'representante',
            ],
        );

        $student->representatives()->syncWithoutDetaching([$representative->id]);

        $adminUser->roles()->syncWithoutDetaching([$roleAdmin->id]);
        $teacherUser->roles()->syncWithoutDetaching([$roleTeacher->id]);
        $studentUser->roles()->syncWithoutDetaching([$roleStudent->id]);
        $repUser->roles()->syncWithoutDetaching([$roleRepresentative->id]);

        foreach (['2026-Q1', '2026-Q2'] as $periodCode) {
            Period::firstOrCreate(
                ['campus_id' => $campus->id, 'code' => $periodCode],
                ['status' => 'active'],
            );
        }

        foreach ([
            [['mon', 'wed', 'fri'], '08:00', '10:00'],
            [['tue', 'thu'], '14:00', '16:00'],
        ] as [$days, $startsAt, $endsAt]) {
            ScheduleTemplate::updateOrCreate(
                ['campus_id' => $campus->id, 'starts_at' => $startsAt, 'ends_at' => $endsAt],
                ['days' => $days, 'status' => 'active'],
            );
        }
    }
}
