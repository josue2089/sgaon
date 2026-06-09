<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Representative;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\HistoricalStudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\HistoricalTestSpreadsheetBuilder;
use Tests\TestCase;

class HistoricalStudentImportTest extends TestCase
{
    use RefreshDatabase;

    private HistoricalTestSpreadsheetBuilder $spreadsheetBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spreadsheetBuilder = new HistoricalTestSpreadsheetBuilder;
    }

    public function test_wide_preview_parses_enrollment_date_serial_status_and_representative(): void
    {
        $campus = $this->createCampus('PICACHO', 'Picacho');
        $path = $this->makeWideSpreadsheet('Matricula Egresada', [
            [
                'María López',
                'V12345678',
                '41000',
                'maria@example.com',
                '04141234567',
                'Calle 1',
                'EXP-001',
                'HS3B',
                '44927',
                'Juan López',
                'V87654321',
                'juan@example.com',
                '04149876543',
            ],
        ]);

        $preview = app(HistoricalStudentImportService::class)->buildPreview($path, $campus->id);

        $this->assertSame(1, $preview->validCount());
        $row = $preview->rows[0];
        $this->assertSame('graduated', $row->status);
        $this->assertSame('2023-01-01', $row->enrollmentDate);
        $this->assertSame('María', $row->firstName);
        $this->assertSame('López', $row->lastName);
        $this->assertSame('Juan López', $row->representativeName);
    }

    public function test_ledger_preview_parses_contract_and_inactive_status(): void
    {
        $campus = $this->createCampus('PICACHO', 'Picacho');
        $path = $this->makeLedgerSpreadsheet([
            ['42005', 'V11111111', 'C-2015-01', 'Pedro Gómez', 'Ana Gómez', '04141111111', '6'],
        ]);

        $preview = app(HistoricalStudentImportService::class)->buildPreview($path, $campus->id);

        $this->assertSame(1, $preview->validCount());
        $row = $preview->rows[0];
        $this->assertSame('inactive', $row->status);
        $this->assertSame('C-2015-01', $row->contractNumber);
        $this->assertNotNull($row->enrollmentDate);
        $this->assertSame(6, $row->installments);
    }

    public function test_preview_without_program_match_is_valid_with_warning(): void
    {
        $campus = $this->createCampus('PICACHO', 'Picacho');
        $path = $this->makeWideSpreadsheet('Matriculas de 22-24', [
            [
                'Ana Ruiz',
                'V22222222',
                '',
                '',
                '',
                '',
                'EXP-99',
                'UNKNOWN-LEVEL',
                '01/06/2022',
                '',
                '',
                '',
                '',
            ],
        ]);

        $preview = app(HistoricalStudentImportService::class)->buildPreview($path, $campus->id);

        $this->assertSame(1, $preview->validCount());
        $this->assertNull($preview->rows[0]->registrationProgramId);
        $this->assertStringContainsString('UNKNOWN-LEVEL', implode(' ', $preview->rows[0]->warnings));
    }

    public function test_import_creates_student_with_representative(): void
    {
        $campus = $this->createCampus('PICACHO', 'Picacho');
        $path = $this->makeWideSpreadsheet('Matricula Egresada', [
            [
                'Carlos Díaz',
                'V33333333',
                '',
                'carlos@example.com',
                '',
                '',
                'EXP-33',
                'PRIMARY 1',
                '15/03/2021',
                'Laura Díaz',
                'V44444444',
                'laura@example.com',
                '04142222222',
            ],
        ]);

        $service = app(HistoricalStudentImportService::class);
        $preview = $service->buildPreview($path, $campus->id);
        $result = $service->import($preview);

        $this->assertSame(1, $result->created);
        $student = Student::query()->where('document_id', 'V33333333')->firstOrFail();
        $this->assertSame('graduated', $student->status);
        $this->assertSame('2021-03-15', $student->enrollment_date->format('Y-m-d'));
        $this->assertTrue($student->representatives()->where('first_name', 'Laura')->exists());
    }

    public function test_activate_historical_student_shows_in_active_index(): void
    {
        $campus = $this->createCampus();
        $admin = $this->createCampusAdmin($campus);
        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Hist',
            'last_name' => 'Activate',
            'status' => 'graduated',
            'enrollment_date' => '2020-05-10',
        ]);

        $this->actingAs($admin)
            ->post(route('students.historical.activate', $student))
            ->assertRedirect(route('students.historical.index'));

        $student->refresh();
        $this->assertSame('active', $student->status);

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee($student->full_name);

        $this->assertTrue(
            AuditLog::query()->where('action', 'students.historical.activate')->exists(),
        );
    }

    public function test_move_active_to_historical_hides_from_active_list(): void
    {
        $campus = $this->createCampus();
        $admin = $this->createCampusAdmin($campus);
        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'status' => 'active',
            'first_name' => 'HistMove',
            'last_name' => 'TestUser',
        ]);

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('HistMove TestUser');

        $this->actingAs($admin)
            ->post(route('students.move-to-historical', $student), ['status' => 'inactive'])
            ->assertRedirect(route('students.index'));

        $student->refresh();
        $this->assertSame('inactive', $student->status);
        $this->assertSame(
            0,
            Student::query()->where('campus_id', $campus->id)->where('status', 'active')->where('id', $student->id)->count(),
        );

        $this->actingAs($admin)
            ->get(route('students.historical.index'))
            ->assertOk()
            ->assertSee('HistMove TestUser');

        $this->assertTrue(
            AuditLog::query()->where('action', 'students.move_to_historical')->exists(),
        );
    }

    public function test_historical_index_filters_by_year(): void
    {
        $campus = $this->createCampus();
        $admin = $this->createCampusAdmin($campus);

        Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Year',
            'last_name' => '2020',
            'status' => 'graduated',
            'enrollment_date' => '2020-01-15',
        ]);
        Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Year',
            'last_name' => '2023',
            'status' => 'inactive',
            'enrollment_date' => '2023-08-20',
        ]);

        $this->actingAs($admin)
            ->get(route('students.historical.index', ['year' => 2020]))
            ->assertOk()
            ->assertSee('Year 2020')
            ->assertDontSee('Year 2023');
    }

    public function test_students_index_defaults_to_active_only(): void
    {
        $campus = $this->createCampus();
        $admin = $this->createCampusAdmin($campus);

        Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'ActiveOnly',
            'last_name' => 'User',
            'status' => 'active',
        ]);
        Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'NotInDefault',
            'last_name' => 'User',
            'status' => 'graduated',
        ]);

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('ActiveOnly User')
            ->assertDontSee('NotInDefault User');
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
    private function makeWideSpreadsheet(string $sheetName, array $rows): string
    {
        $path = storage_path('framework/testing/hist-wide-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $this->spreadsheetBuilder->buildWide($path, $sheetName, $rows);

        return $path;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeLedgerSpreadsheet(array $rows): string
    {
        $path = storage_path('framework/testing/hist-ledger-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $this->spreadsheetBuilder->buildLedger($path, $rows);

        return $path;
    }
}
