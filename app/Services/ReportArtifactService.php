<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use FPDF;

final class ReportArtifactService
{
    private const MAX_ROWS = 200;

    public function pdf(string $title, array $columns, array $rows): string
    {
        $columns = $this->columns($columns);
        $rows = array_slice(array_values($rows), 0, self::MAX_ROWS + 1);
        $truncated = count($rows) > self::MAX_ROWS;
        $rows = array_slice($rows, 0, self::MAX_ROWS);
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetTitle($this->pdfText($this->text($title, 180)));
        $pdf->SetAuthor('OEMS');
        $pdf->SetCompression(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $width = 277 / max(1, count($columns));
        $renderHeader = function () use ($pdf, $title, $columns, $width): void {
            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->Cell(0, 8, $this->pdfText($this->text($title, 180)), 0, 1);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(80, 90, 105);
            $pdf->Cell(0, 5, 'Aggregate operational data. Generated ' . gmdate('Y-m-d H:i') . ' UTC.', 0, 1);
            $pdf->SetTextColor(20, 32, 51);
            $pdf->SetFillColor(232, 237, 255);
            $pdf->SetFont('Helvetica', 'B', 6.5);
            foreach ($columns as $label) {
                $pdf->Cell($width, 7, $this->pdfText($this->short($label, $width)), 1, 0, 'L', true);
            }
            $pdf->Ln();
        };
        $pdf->AddPage();
        $renderHeader();
        $pdf->SetFont('Helvetica', '', 6.5);
        foreach ($rows as $row) {
            if ($pdf->GetY() > 190) {
                $pdf->AddPage();
                $renderHeader();
                $pdf->SetFont('Helvetica', '', 6.5);
            }
            foreach (array_keys($columns) as $key) {
                $value = is_array($row) ? ($row[$key] ?? '') : '';
                $pdf->Cell($width, 6, $this->pdfText($this->short($this->text($value, 500), $width)), 1);
            }
            $pdf->Ln();
        }
        if ($rows === []) {
            $pdf->Cell(0, 8, 'No report rows matched these filters.', 1, 1);
        }
        if ($truncated) {
            $pdf->Ln(2);
            $pdf->SetFont('Helvetica', 'I', 7);
            $pdf->MultiCell(0, 4, 'This PDF is limited to 200 rows. Use the CSV or Excel XML export for the complete filtered dataset.');
        }

        return $pdf->Output('S');
    }

    public function spreadsheetXml(string $title, array $columns, array $rows): string
    {
        $columns = $this->columns($columns);
        $rows = array_slice(array_values($rows), 0, self::MAX_ROWS + 1);
        $truncated = count($rows) > self::MAX_ROWS;
        $rows = array_slice($rows, 0, self::MAX_ROWS);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?mso-application progid="Excel.Sheet"?>' . "\n"
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Title>' . $this->xml($this->text($title, 180)) . '</Title></DocumentProperties>'
            . '<Worksheet ss:Name="Report"><Table>';
        $xml .= $this->xmlRow(array_values($columns));
        foreach ($rows as $row) {
            $cells = [];
            foreach (array_keys($columns) as $key) {
                $cells[] = is_array($row) ? ($row[$key] ?? '') : '';
            }
            $xml .= $this->xmlRow($cells);
        }
        if ($truncated) {
            $xml .= $this->xmlRow(['Limited to 200 rows. Use CSV for the complete filtered dataset.']);
        }

        return $xml . '</Table></Worksheet></Workbook>';
    }

    private function columns(array $columns): array
    {
        $safe = [];
        foreach (array_slice($columns, 0, 20, true) as $key => $label) {
            if (!is_string($key) || !is_scalar($label)) {
                continue;
            }
            $safe[$key] = $this->text($label, 100);
        }

        return $safe === [] ? ['value' => 'Value'] : $safe;
    }

    private function xmlRow(array $cells): string
    {
        $xml = '<Row>';
        foreach ($cells as $cell) {
            $xml .= '<Cell><Data ss:Type="String">' . $this->xml($this->text($cell, 500)) . '</Data></Cell>';
        }

        return $xml . '</Row>';
    }

    private function text(mixed $value, int $limit): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $limit);
    }

    private function short(string $value, float $width): string
    {
        $characters = max(4, (int) floor($width / 2.1));

        return mb_strimwidth($value, 0, $characters, '...');
    }

    private function pdfText(string $value): string
    {
        $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);

        return is_string($converted) ? $converted : '';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
