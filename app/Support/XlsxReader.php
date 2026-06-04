<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class XlsxReader
{
    public function readRows(string $path, int $sheetIndex = 0): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->resolveSheetPath($zip, $sheetIndex);
            $xml = $zip->getFromName($sheetPath);

            if ($xml === false) {
                throw new RuntimeException('No se encontró la hoja solicitada en el XLSX.');
            }

            return $this->rowsFromSheet($xml, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    private function resolveSheetPath(ZipArchive $zip, int $sheetIndex): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if ($workbook === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $doc = new DOMDocument();
        if (! $doc->loadXML($workbook)) {
            return 'xl/worksheets/sheet1.xml';
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $sheets = $xpath->query('//x:sheet');
        if ($sheets->length === 0) {
            return 'xl/worksheets/sheet1.xml';
        }

        $targetSheet = $sheets->item(min($sheetIndex, $sheets->length - 1));
        $relationshipId = $targetSheet?->getAttribute('r:id');
        if ($relationshipId === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relsDoc = new DOMDocument();
        if (! $relsDoc->loadXML($rels)) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relsXpath = new DOMXPath($relsDoc);
        $relsXpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($relsXpath->query('//rel:Relationship') as $relationship) {
            if ($relationship->getAttribute('Id') !== $relationshipId) {
                continue;
            }

            $target = ltrim($relationship->getAttribute('Target'), '/');

            return str_starts_with($target, 'worksheets/')
                ? 'xl/'.$target
                : 'xl/worksheets/'.$target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw === false) {
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
            $cellsByColumn = [];
            $maxColumn = -1;
            $sequentialIndex = 0;
            foreach ($xpath->query('./x:c', $row) as $cell) {
                $reference = $cell->getAttribute('r');
                $columnIndex = $reference !== ''
                    ? $this->columnIndexFromReference($reference)
                    : $sequentialIndex++;
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
                $cellsByColumn[$columnIndex] = trim($value);
                $maxColumn = max($maxColumn, $columnIndex);
            }

            $cells = [];
            for ($i = 0; $i <= $maxColumn; $i++) {
                $cells[] = $cellsByColumn[$i] ?? '';
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    private function columnIndexFromReference(string $cellReference): int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($cellReference), $matches)) {
            return 0;
        }

        $letters = $matches[1];
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
