<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\Representative;
use App\Models\Student;
use Illuminate\Console\Command;
use DOMDocument;
use DOMXPath;
use ZipArchive;

class ImportStudentsHistorical extends Command
{
    protected $signature = 'import:students-historical {--file=} {--campus=PICACHO}';

    protected $description = 'Importa histórico de alumnos desde XLSX.';

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

        $zip = new ZipArchive();

        if ($zip->open($file) !== true) {
            $this->error('No se pudo abrir XLSX.');

            return self::FAILURE;
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetNames = ['sheet1.xml', 'sheet2.xml', 'sheet3.xml', 'sheet4.xml'];

        $count = 0;

        foreach ($sheetNames as $sheetName) {
            $path = 'xl/worksheets/'.$sheetName;
            $xml = $zip->getFromName($path);

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
                $fullName = trim((string) ($mapped['Nombres y Apellidos'] ?? ''));

                if ($fullName === '' || in_array(strtolower($fullName), ['datos del alumno', 'nombres y apellidos'], true)) {
                    continue;
                }

                [$firstName, $lastName] = $this->splitName($fullName);
                $documentId = trim((string) ($mapped['Documento de Identidad'] ?? ''));

                $student = Student::updateOrCreate(
                    ['campus_id' => $campus->id, 'document_id' => $documentId !== '' ? $documentId : null, 'first_name' => $firstName, 'last_name' => $lastName],
                    [
                        'email' => $this->cleanEmail($mapped['Correo'] ?? null),
                        'phone' => trim((string) ($mapped['Telefono'] ?? '')) ?: null,
                        'address' => trim((string) ($mapped['Dirección'] ?? '')) ?: null,
                        'status' => $this->detectStatus($mapped),
                    ],
                );

                $repName = trim((string) ($mapped['Nombre y Apellido'] ?? ''));
                if ($repName !== '') {
                    [$rf, $rl] = $this->splitName($repName);
                    $rep = Representative::updateOrCreate(
                        ['campus_id' => $campus->id, 'document_id' => trim((string) ($mapped['Documento de Identidad2'] ?? '')) ?: null, 'first_name' => $rf, 'last_name' => $rl],
                        [
                            'email' => $this->cleanEmail($mapped['Correo2'] ?? null),
                            'phone' => trim((string) ($mapped['Telefono.1'] ?? $mapped['Telefono'] ?? '')) ?: null,
                            'relation' => 'representante',
                        ],
                    );
                    $student->representatives()->syncWithoutDetaching([$rep->id]);
                }

                $count++;
            }
        }

        $zip->close();

        $this->info("Importación de alumnos completada. Filas procesadas: {$count}");

        return self::SUCCESS;
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

    private function cleanEmail(mixed $value): ?string
    {
        $email = trim((string) $value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
    }

    private function detectStatus(array $mapped): string
    {
        $line = strtolower(implode(' ', array_filter(array_map('strval', $mapped))));

        if (str_contains($line, 'retirado') || str_contains($line, 'egresado')) {
            return 'inactive';
        }

        return 'active';
    }
}
