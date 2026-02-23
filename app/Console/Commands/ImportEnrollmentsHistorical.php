<?php

namespace App\Console\Commands;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use ZipArchive;

class ImportEnrollmentsHistorical extends Command
{
    protected $signature = 'import:enrollments-historical {--file=} {--campus=PICACHO}';

    protected $description = 'Importa inscripciones históricas desde XLSX y crea cursos/grupos legacy.';

    public function handle(): int
    {
        $file = (string) $this->option('file');

        if ($file === '' || ! file_exists($file)) {
            $this->error('Debe indicar --file con una ruta válida.');

            return self::FAILURE;
        }

        $campus = Campus::firstOrCreate(
            ['code' => strtoupper((string) $this->option('campus'))],
            ['name' => 'Sede Picacho', 'city' => 'San Antonio de los Altos', 'country' => 'Venezuela', 'status' => 'active'],
        );

        $legacyLevel = AcademicLevel::firstOrCreate(
            ['campus_id' => $campus->id, 'name' => 'Legacy'],
            ['code' => 'LEGACY', 'sort_order' => 999, 'description' => 'Niveles importados desde histórico'],
        );

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            $this->error('No se pudo abrir XLSX.');

            return self::FAILURE;
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetNames = ['sheet1.xml', 'sheet2.xml', 'sheet3.xml', 'sheet4.xml'];

        $processed = 0;
        $enrollmentsCreated = 0;

        foreach ($sheetNames as $sheetName) {
            $xml = $zip->getFromName('xl/worksheets/'.$sheetName);
            if (! $xml) {
                continue;
            }

            $rows = $this->rowsFromSheet($xml, $sharedStrings);
            $headers = [];

            foreach ($rows as $row) {
                if ($headers === [] && $this->looksLikeHeader($row)) {
                    $headers = $row;
                    continue;
                }

                if ($headers === []) {
                    continue;
                }

                $mapped = $this->mapRow($headers, $row);
                $student = $this->findStudent($mapped, $campus->id);
                if (! $student) {
                    continue;
                }

                $schedule = trim((string) ($mapped['Horario'] ?? '')) ?: null;
                $entries = $this->extractEnrollmentEntries($row);

                foreach ($entries as $entry) {
                    $levelCode = $entry['level'];
                    $enrolledAt = $entry['date'] ?? $student->enrollment_date?->format('Y-m-d');
                    $period = $enrolledAt ? substr($enrolledAt, 0, 4) : 'HIST';

                    $course = Course::firstOrCreate(
                        ['campus_id' => $campus->id, 'name' => $levelCode],
                        [
                            'academic_level_id' => $legacyLevel->id,
                            'code' => preg_replace('/[^A-Z0-9]+/', '_', strtoupper($levelCode)) ?: strtoupper($levelCode),
                            'status' => 'active',
                        ],
                    );

                    $groupName = trim($levelCode.' '.($schedule ?: 'SIN HORARIO').' '.$period);

                    $group = Group::firstOrCreate(
                        [
                            'campus_id' => $campus->id,
                            'course_id' => $course->id,
                            'name' => $groupName,
                            'period' => $period,
                            'schedule' => $schedule,
                        ],
                        [
                            'status' => 'active',
                            'start_date' => $enrolledAt,
                        ],
                    );

                    $enrollment = Enrollment::firstOrCreate(
                        [
                            'student_id' => $student->id,
                            'group_id' => $group->id,
                        ],
                        [
                            'campus_id' => $campus->id,
                            'enrolled_at' => $enrolledAt,
                            'status' => $student->status === 'active' ? 'active' : 'inactive',
                            'progress' => 0,
                        ],
                    );

                    if ($enrollment->wasRecentlyCreated) {
                        $enrollmentsCreated++;
                    }
                }

                $processed++;
            }
        }

        $zip->close();

        $this->info("Importación de inscripciones completada. Alumnos procesados: {$processed}. Inscripciones nuevas: {$enrollmentsCreated}");

        return self::SUCCESS;
    }

    private function findStudent(array $mapped, int $campusId): ?Student
    {
        $document = trim((string) ($mapped['Documento de Identidad'] ?? ''));
        $fullName = trim((string) ($mapped['Nombres y Apellidos'] ?? ''));

        if ($document !== '') {
            $student = Student::where('campus_id', $campusId)->where('document_id', $document)->first();
            if ($student) {
                return $student;
            }
        }

        [$firstName, $lastName] = $this->splitName($fullName);

        return Student::where('campus_id', $campusId)
            ->where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->first();
    }

    private function extractEnrollmentEntries(array $row): array
    {
        $pairs = [
            [16, 47],
            [48, 47],
            [51, 50],
            [54, 53],
            [57, 56],
            [60, 59],
            [63, 62],
            [66, 65],
        ];

        $entries = [];

        foreach ($pairs as [$levelIndex, $dateIndex]) {
            $level = $this->normalizeLevel($row[$levelIndex] ?? null);
            if (! $level) {
                continue;
            }

            $date = $this->normalizeDate($row[$dateIndex] ?? null);
            $key = $level.'|'.($date ?? '');

            $entries[$key] = [
                'level' => $level,
                'date' => $date,
            ];
        }

        return array_values($entries);
    }

    private function normalizeLevel(mixed $value): ?string
    {
        $raw = strtoupper(trim((string) $value));

        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '@') || str_contains($raw, ':')) {
            return null;
        }

        if (in_array($raw, ['ACTIVO', 'RETIRADO', 'CONTADO', 'FINANCIADO'], true)) {
            return null;
        }

        if (! preg_match('/[A-Z]/', $raw) || ! preg_match('/\d/', $raw)) {
            return null;
        }

        return preg_replace('/\s+/', ' ', $raw);
    }

    private function normalizeDate(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $days = (int) floor((float) $raw);
            if ($days > 20000 && $days < 60000) {
                $date = \DateTime::createFromFormat('Y-m-d', '1899-12-30');
                if ($date) {
                    $date->modify("+{$days} days");

                    return $date->format('Y-m-d');
                }
            }
        }

        $timestamp = strtotime($raw);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if (! $raw) {
            return [];
        }

        $doc = new DOMDocument();
        if (! $doc->loadXML($raw)) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $result = [];
        foreach ($xpath->query('//x:si') as $si) {
            $chunks = [];
            foreach ($xpath->query('.//x:t', $si) as $t) {
                $chunks[] = $t->textContent;
            }
            $result[] = implode('', $chunks);
        }

        return $result;
    }

    private function rowsFromSheet(string $xmlRaw, array $sharedStrings): array
    {
        $doc = new DOMDocument();
        if (! $doc->loadXML($xmlRaw)) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($xpath->query('//x:sheetData/x:row') as $row) {
            $cells = [];
            foreach ($xpath->query('./x:c', $row) as $cell) {
                $value = '';
                $type = $cell->getAttribute('t');
                $vNode = $xpath->query('./x:v', $cell)->item(0);
                if ($vNode) {
                    $raw = $vNode->textContent;
                    $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
                } else {
                    $inlineNode = $xpath->query('./x:is/x:t', $cell)->item(0);
                    if ($inlineNode) {
                        $value = $inlineNode->textContent;
                    }
                }
                $cells[] = trim($value);
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    private function looksLikeHeader(array $row): bool
    {
        $line = strtolower(implode(' | ', $row));

        return str_contains($line, 'nombres y apellidos') && str_contains($line, 'documento de identidad');
    }

    private function mapRow(array $headers, array $row): array
    {
        $out = [];
        foreach ($headers as $i => $header) {
            $key = trim((string) $header);
            if ($key === '') {
                $key = 'col_'.$i;
            }
            if (isset($out[$key])) {
                $key .= '.'.$i;
            }
            $out[$key] = $row[$i] ?? null;
        }

        return $out;
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) <= 1) {
            return [$name, ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }
}
