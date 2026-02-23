<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Student;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use ZipArchive;

class ImportFinanceLedger extends Command
{
    protected $signature = 'import:finance-ledger {--file=} {--campus=PICACHO}';

    protected $description = 'Importa ledger financiero desde CSV o XLSX histórico.';

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

        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return $this->importFromCsv($file, $campus->id);
        }

        if ($ext === 'xlsx') {
            return $this->importFromXlsx($file, $campus->id);
        }

        $this->error('Formato no soportado. Usa CSV o XLSX.');

        return self::FAILURE;
    }

    private function importFromCsv(string $file, int $campusId): int
    {
        $fp = fopen($file, 'r');
        if (! $fp) {
            $this->error('No se pudo abrir CSV.');

            return self::FAILURE;
        }

        $headers = fgetcsv($fp) ?: [];
        $count = 0;

        while (($row = fgetcsv($fp)) !== false) {
            $data = array_combine($headers, $row);
            if (! $data) {
                continue;
            }

            $student = Student::where('document_id', trim((string) ($data['document_id'] ?? '')))->first();
            if (! $student) {
                continue;
            }

            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $status = trim((string) ($data['status'] ?? 'pending'));
            $charge = $this->findOrCreateCharge(
                $campusId,
                $student->id,
                trim((string) ($data['concept'] ?? 'Mensualidad')),
                $amount,
                $data['due_date'] ?: null,
                $status,
            );

            if (in_array($status, ['paid', 'partial'], true)) {
                $this->createPaymentAndReceipt($campusId, $student->id, $charge->id, $amount, $data['due_date'] ?: date('Y-m-d'), 'import_csv');
            }

            $count++;
        }

        fclose($fp);

        $this->info("Importación financiera CSV completada. Registros procesados: {$count}");

        return self::SUCCESS;
    }

    private function importFromXlsx(string $file, int $campusId): int
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            $this->error('No se pudo abrir XLSX.');

            return self::FAILURE;
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetNames = ['sheet1.xml', 'sheet2.xml', 'sheet3.xml', 'sheet4.xml'];

        $processed = 0;

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
                $name = trim((string) ($mapped['Nombres y Apellidos'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $student = $this->findStudentForFinanceRow($mapped, $campusId);
                if (! $student) {
                    continue;
                }

                $monthly = $this->extractMonthlyAmounts($headers, $row);

                foreach ($monthly as $entry) {
                    $charge = $this->findOrCreateCharge(
                        $campusId,
                        $student->id,
                        $entry['concept'],
                        $entry['amount'],
                        $entry['due_date'],
                        'paid',
                    );

                    $this->createPaymentAndReceipt(
                        $campusId,
                        $student->id,
                        $charge->id,
                        $entry['amount'],
                        $entry['due_date'],
                        'import_xlsx',
                    );
                }

                $processed++;
            }
        }

        $zip->close();

        $this->info("Importación financiera XLSX completada. Alumnos procesados: {$processed}");

        return self::SUCCESS;
    }

    private function findStudentForFinanceRow(array $mapped, int $campusId): ?Student
    {
        $document = trim((string) ($mapped['Documento de Identidad'] ?? ''));
        $name = trim((string) ($mapped['Nombres y Apellidos'] ?? ''));

        if ($document !== '') {
            $student = Student::where('campus_id', $campusId)->where('document_id', $document)->first();
            if ($student) {
                return $student;
            }
        }

        [$firstName, $lastName] = $this->splitName($name);

        return Student::where('campus_id', $campusId)
            ->where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->first();
    }

    private function extractMonthlyAmounts(array $headers, array $row): array
    {
        $months = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        $entries = [];

        foreach ($headers as $index => $headerRaw) {
            $header = strtolower(trim((string) $headerRaw));
            if ($header === '') {
                continue;
            }

            $monthNumber = null;
            foreach ($months as $label => $number) {
                if (str_contains($header, $label)) {
                    $monthNumber = $number;
                    break;
                }
            }

            if (! $monthNumber || $index < 21 || $index > 44) {
                continue;
            }

            $value = $this->toAmount($row[$index] ?? null);
            if ($value <= 0) {
                continue;
            }

            $year = $this->detectYearForColumn($index, $header);
            $dueDate = sprintf('%04d-%02d-01', $year, $monthNumber);

            $entries[] = [
                'concept' => 'Mensualidad '.ucfirst(array_search($monthNumber, $months, true)).' '.$year,
                'amount' => $value,
                'due_date' => $dueDate,
            ];
        }

        return $entries;
    }

    private function detectYearForColumn(int $index, string $header): int
    {
        if (preg_match('/20\d{2}/', $header, $match)) {
            return (int) $match[0];
        }

        if ($index >= 21 && $index <= 32) {
            return 2022;
        }

        if ($index >= 33 && $index <= 44) {
            return 2023;
        }

        return 2024;
    }

    private function findOrCreateCharge(int $campusId, int $studentId, string $concept, float $amount, ?string $dueDate, string $status): Charge
    {
        $normalizedDueDate = $this->normalizeDateTime($dueDate);

        $existing = Charge::where('campus_id', $campusId)
            ->where('student_id', $studentId)
            ->where('concept', $concept)
            ->where('due_date', $normalizedDueDate)
            ->first();

        if ($existing) {
            $newAmount = max((float) $existing->amount, $amount);
            if ((float) $existing->amount !== $newAmount || $existing->status !== $status) {
                $existing->amount = $newAmount;
                $existing->status = $status;
                $existing->save();
            }

            return $existing;
        }

        return Charge::create([
            'campus_id' => $campusId,
            'student_id' => $studentId,
            'concept' => $concept,
            'amount' => $amount,
            'due_date' => $normalizedDueDate,
            'status' => $status,
        ]);
    }

    private function createPaymentAndReceipt(int $campusId, int $studentId, int $chargeId, float $amount, string $paidAt, string $method): void
    {
        $normalizedPaidAt = $this->normalizeDateTime($paidAt);
        $reference = sprintf('IMP-%d-%d-%s-%.2f', $campusId, $studentId, $normalizedPaidAt, $amount);

        $payment = Payment::where('campus_id', $campusId)
            ->where('student_id', $studentId)
            ->where('charge_id', $chargeId)
            ->where('reference', $reference)
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'campus_id' => $campusId,
                'student_id' => $studentId,
                'charge_id' => $chargeId,
                'amount' => $amount,
                'paid_at' => $normalizedPaidAt,
                'method' => $method,
                'reference' => $reference,
            ]);
        }

        if (! $payment->receipt()->exists()) {
            Receipt::create([
                'campus_id' => $campusId,
                'payment_id' => $payment->id,
                'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
                'issued_at' => $normalizedPaidAt,
            ]);
        }
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

    private function toAmount(mixed $value): float
    {
        $raw = trim((string) $value);
        if ($raw === '' || str_contains($raw, '#REF')) {
            return 0;
        }

        $normalized = str_replace([',', '$', ' '], ['', '', ''], $raw);

        return is_numeric($normalized) ? (float) $normalized : 0;
    }

    private function normalizeDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return date('Y-m-d 00:00:00', strtotime($value));
    }
}
