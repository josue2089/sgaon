<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentBulkImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\CclTestSpreadsheetBuilder;
use Tests\TestCase;

class StudentBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private CclTestSpreadsheetBuilder $spreadsheetBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spreadsheetBuilder = new CclTestSpreadsheetBuilder;
    }

    public function test_preview_maps_hs_nivel_to_high_school_program(): void
    {
        $campus = $this->createCampus();
        $path = $this->makeSpreadsheet([
            ['1', 'Ana', '', 'García', '10', 'F', 'HS3B', 'Lun-Mier', 'Activo'],
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview($path, $campus->id);

        $this->assertSame(1, $preview->validCount());
        $hs = Program::query()->where('code', 'HS')->firstOrFail();
        $this->assertSame($hs->id, $preview->rows[0]->registrationProgramId);
        $this->assertSame('HighSchool', $preview->rows[0]->programName);
    }

    public function test_preview_maps_primary_nivel_to_primary_program(): void
    {
        $campus = $this->createCampus();
        $path = $this->makeSpreadsheet([
            ['2', 'Luis', '', 'Pérez', '8', 'M', 'PR2B', 'Mar-Jue', 'Activo'],
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview($path, $campus->id);

        $this->assertSame(1, $preview->validCount());
        $primary = Program::query()->where('code', 'PRIMARY')->firstOrFail();
        $this->assertSame($primary->id, $preview->rows[0]->registrationProgramId);
    }

    public function test_preview_fails_rob_nivel_without_robotics_program(): void
    {
        $campus = $this->createCampus();
        $path = $this->makeSpreadsheet([
            ['3', 'Max', '', 'Lee', '12', 'M', 'ROB6', 'Sab', 'Activo'],
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview($path, $campus->id);

        $this->assertSame(0, $preview->validCount());
        $this->assertStringContainsString('Robótica', implode(' ', $preview->rows[0]->errors));
    }

    public function test_preview_maps_rob_nivel_to_robotics_program_in_database(): void
    {
        Program::query()->create([
            'name' => 'Robótica',
            'code' => 'ROB',
            'status' => 'active',
            'description' => null,
        ]);

        $campus = $this->createCampus();
        $path = $this->makeSpreadsheet([
            ['4', 'Max', '', 'Lee', '12', 'M', 'ROB6', 'Sab', 'Activo'],
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview($path, $campus->id);

        $this->assertSame(1, $preview->validCount());
        $this->assertSame('Robótica', $preview->rows[0]->programName);
        $this->assertSame(
            Program::query()->where('code', 'ROB')->value('id'),
            $preview->rows[0]->registrationProgramId,
        );
    }

    public function test_import_updates_existing_student_by_name(): void
    {
        $campus = $this->createCampus();
        $hs = Program::query()->where('code', 'HS')->firstOrFail();
        $primary = Program::query()->where('code', 'PRIMARY')->firstOrFail();

        Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Old',
            'last_name' => 'Name',
            'status' => 'active',
            'registration_program_id' => $hs->id,
        ]);

        $path = $this->makeSpreadsheet([
            ['1', 'Old', '', 'Name', '11', 'M', 'PR2B', '', 'Inactivo'],
        ]);

        $service = app(StudentBulkImportService::class);
        $preview = $service->buildPreview($path, $campus->id);
        $this->assertSame('update', $preview->rows[0]->action);

        $result = $service->import($preview);

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->updated);

        $student = Student::query()
            ->where('campus_id', $campus->id)
            ->where('first_name', 'Old')
            ->where('last_name', 'Name')
            ->firstOrFail();
        $this->assertSame('inactive', $student->status);
        $this->assertSame($primary->id, $student->registration_program_id);
    }

    public function test_campus_admin_cannot_import_to_other_campus(): void
    {
        $campusA = $this->createCampus('CA', 'Campus A');
        $campusB = $this->createCampus('CB', 'Campus B');
        $admin = $this->createCampusAdmin($campusA);

        $path = $this->makeSpreadsheet([
            ['1', 'Ana', '', 'García', '', 'F', 'HS1A', '', 'Activo'],
        ]);

        $file = new UploadedFile($path, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($admin)->post(route('students.import.preview'), [
            'campus_id' => $campusB->id,
            'file' => $file,
        ]);

        $response->assertForbidden();
    }

    public function test_import_flow_via_http_creates_student_and_audit_log(): void
    {
        $campus = $this->createCampus();
        $admin = $this->createCampusAdmin($campus);
        $path = $this->makeSpreadsheet([
            ['10', 'Sara', 'María', 'López', '9', 'F', 'HS1A', '', 'Activo'],
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'ccl.xlsx',
            file_get_contents($path),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($admin)
            ->post(route('students.import.preview'), [
                'campus_id' => $campus->id,
                'file' => $file,
            ])
            ->assertOk()
            ->assertSee('Importar 1 alumno');

        $this->actingAs($admin)
            ->post(route('students.import.store'))
            ->assertRedirect(route('students.index'));

        $student = Student::query()
            ->where('campus_id', $campus->id)
            ->where('first_name', 'Sara María')
            ->where('last_name', 'López')
            ->firstOrFail();
        $this->assertSame('Sara María', $student->first_name);
        $this->assertSame('López', $student->last_name);
        $this->assertSame(
            Program::query()->where('code', 'HS')->value('id'),
            $student->registration_program_id,
        );

        $this->assertTrue(
            AuditLog::query()->where('action', 'students.bulk_import')->exists(),
        );
    }

    public function test_students_index_shows_import_link(): void
    {
        $campus = $this->createCampus();
        $admin = $this->createCampusAdmin($campus);

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Importar');
    }

    private function createCampus(string $code = 'TST', string $name = 'Test Campus'): Campus
    {
        return Campus::query()->create([
            'name' => $name,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    private function createCampusAdmin(Campus $campus): User
    {
        $admin = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
        ]);
        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeSpreadsheet(array $rows): string
    {
        $path = storage_path('framework/testing/ccl-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $this->spreadsheetBuilder->build($rows, $path);

        return $path;
    }
}
