<?php

namespace Tests\Support;

use ZipArchive;

class HistoricalTestSpreadsheetBuilder
{
    /**
     * @param  list<list<string>>  $dataRows
     */
    public function buildWide(string $path, string $sheetName, array $dataRows): void
    {
        $headers = [
            'Nombres y Apellidos',
            'Documento de Identidad',
            'Fecha de Nacimiento',
            'Correo',
            'Telefono',
            'Dirección',
            'N° Expediente',
            'Nivel',
            'Fecha de inscripción',
            'Nombre y Apellido',
            'Documento de Identidad2',
            'Correo2',
            'Telefono',
        ];

        $allRows = array_merge(
            array_fill(0, 5, array_fill(0, count($headers), '')),
            [$headers],
            $dataRows,
        );

        $this->writeWorkbook($path, [
            ['name' => $sheetName, 'rows' => $allRows],
        ]);
    }

    /**
     * @param  list<list<string>>  $dataRows
     */
    public function buildLedger(string $path, array $dataRows): void
    {
        $headers = [
            'FECHA',
            'DOCUMENTO DE IDENTIDAD',
            'Nº DE CONTRATO',
            'ALUMNO',
            'TITULAR',
            'TELEFONO',
            'Nº Cuotas',
        ];

        $allRows = array_merge(
            array_fill(0, 4, array_fill(0, count($headers), '')),
            [$headers],
            $dataRows,
        );

        $this->writeWorkbook($path, [
            ['name' => 'HISTORICO DE MATRICULA', 'rows' => $allRows],
        ]);
    }

    /**
     * @param  list<array{name: string, rows: list<list<string>>}>  $sheets
     */
    private function writeWorkbook(string $path, array $sheets): void
    {
        $strings = [];
        $stringIndex = [];
        $indexString = function (string $value) use (&$strings, &$stringIndex): int {
            if (! isset($stringIndex[$value])) {
                $stringIndex[$value] = count($strings);
                $strings[] = $value;
            }

            return $stringIndex[$value];
        };

        $sheetEntries = '';
        $rels = '';
        $contentOverrides = '';
        $zipSheets = [];

        foreach ($sheets as $i => $sheet) {
            $sheetNum = $i + 1;
            $relId = 'rId'.$sheetNum;
            $sheetEntries .= '<sheet name="'.htmlspecialchars($sheet['name'], ENT_XML1).'" sheetId="'.$sheetNum.'" r:id="'.$relId.'"/>';
            $rels .= '<Relationship Id="'.$relId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sheetNum.'.xml"/>';
            $contentOverrides .= '<Override PartName="/xl/worksheets/sheet'.$sheetNum.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';

            $sheetRowsXml = '';
            foreach ($sheet['rows'] as $rowNum => $cells) {
                $r = $rowNum + 1;
                $cellsXml = '';
                foreach ($cells as $colNum => $value) {
                    $colLetter = $this->columnLetter($colNum);
                    $ref = $colLetter.$r;
                    if ($value !== '' && is_numeric($value) && ! str_contains((string) $value, '.')) {
                        $cellsXml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                    } else {
                        $idx = $indexString($value !== '' ? (string) $value : ' ');
                        $cellsXml .= '<c r="'.$ref.'" t="s"><v>'.$idx.'</v></c>';
                    }
                }
                $sheetRowsXml .= '<row r="'.$r.'">'.$cellsXml.'</row>';
            }

            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetData>'.$sheetRowsXml.'</sheetData></worksheet>';

            $zipSheets['xl/worksheets/sheet'.$sheetNum.'.xml'] = $sheetXml;
        }

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
        foreach ($strings as $text) {
            $sharedStringsXml .= '<si><t>'.htmlspecialchars($text, ENT_XML1).'</t></si>';
        }
        $sharedStringsXml .= '</sst>';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$contentOverrides
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'<Relationship Id="rId'.(count($sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetEntries.'</sheets></workbook>');

        foreach ($zipSheets as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->close();
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        do {
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }
}
