<?php

namespace App\Support;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Scrittore XLSX minimale senza dipendenze esterne (usa solo ext-zip, già
 * presente nell'immagine Docker). Produce un file .xlsx valido con una scheda,
 * intestazione in grassetto e celle come stringhe inline (sicure per qualsiasi
 * contenuto). Sufficiente per gli export elenco (fornitori, clienti, ecc.).
 *
 * Uso:
 *   return SimpleXlsxWriter::make('Fornitori')
 *       ->headers(['Codice', 'Ragione Sociale'])
 *       ->rows([['F001', 'ACME'], ['F002', 'Beta']])
 *       ->download('fornitori.xlsx');
 */
class SimpleXlsxWriter
{
    private string $sheetName;
    private array $headers = [];
    private array $rows = [];

    public function __construct(string $sheetName = 'Foglio1')
    {
        // Il nome scheda in Excel: max 31 char, niente : \ / ? * [ ]
        $this->sheetName = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $sheetName), 0, 31) ?: 'Foglio1';
    }

    public static function make(string $sheetName = 'Foglio1'): self
    {
        return new self($sheetName);
    }

    public function headers(array $headers): self
    {
        $this->headers = array_values($headers);
        return $this;
    }

    public function rows(array $rows): self
    {
        $this->rows = $rows;
        return $this;
    }

    public function addRow(array $row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    /** Costruisce i byte del file .xlsx. */
    public function build(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());

        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    public function download(string $filename): StreamedResponse
    {
        $bytes = $this->build();

        return Response::stream(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($bytes),
        ]);
    }

    // ── XML parts ─────────────────────────────────────────────────────────

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->esc($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        // s=0 normale, s=1 grassetto (intestazione)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sheet(): string
    {
        $rowsXml = '';
        $rowNum = 1;

        if ($this->headers) {
            $rowsXml .= $this->rowXml($this->headers, $rowNum++, true);
        }
        foreach ($this->rows as $row) {
            $rowsXml .= $this->rowXml(array_values((array) $row), $rowNum++, false);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '</worksheet>';
    }

    private function rowXml(array $cells, int $rowNum, bool $header): string
    {
        $style = $header ? ' s="1"' : '';
        $xml = '<row r="' . $rowNum . '">';
        $col = 0;
        foreach ($cells as $value) {
            $ref = $this->colLetter($col++) . $rowNum;
            $text = $this->esc((string) ($value ?? ''));
            $xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
        }
        $xml .= '</row>';

        return $xml;
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - 1, 26);
        }
        return $letter;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
