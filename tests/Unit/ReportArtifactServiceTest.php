<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\ReportArtifactService;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class ReportArtifactServiceTest extends TestCase
{
    public function testPdfIsBoundedAndContainsOnlyPresentedAggregateColumns(): void
    {
        $service = $this->service();
        $rows = array_fill(0, 205, ['event' => 'Community gathering', 'amount' => '9007199254740993.24']);
        $pdf = $service->pdf('OEMS aggregate report', ['event' => 'Event', 'amount' => 'Amount'], $rows);

        $this->assertTrue(str_starts_with($pdf, '%PDF-'));
        $this->assertTrue(strlen($pdf) > 1000);
        $this->assertFalse(str_contains(strtolower($pdf), 'participant_email'));
        $this->assertFalse(str_contains(strtolower($pdf), 'gateway_response'));
    }

    public function testSpreadsheetXmlUsesStringCellsWithoutFormulaExecutionOrControls(): void
    {
        $service = $this->service();
        $xml = $service->spreadsheetXml('OEMS & report', ['event' => 'Event', 'amount' => 'Amount'], [[
            'event' => "=2+2\0<script>alert(1)</script>",
            'amount' => '9007199254740993.24',
        ]]);

        $this->assertTrue(str_starts_with($xml, '<?xml version="1.0" encoding="UTF-8"?>'));
        $this->assertTrue(str_contains($xml, 'urn:schemas-microsoft-com:office:spreadsheet'));
        $this->assertTrue(str_contains($xml, 'ss:Type="String"'));
        $this->assertTrue(str_contains($xml, '=2+2&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertTrue(str_contains($xml, '9007199254740993.24'));
        $this->assertFalse(str_contains($xml, 'ss:Formula'));
        $this->assertFalse(str_contains($xml, "\0"));
        $this->assertFalse(str_contains($xml, '<script>'));
    }

    private function service(): ReportArtifactService
    {
        if (!class_exists(ReportArtifactService::class)) {
            throw new RuntimeException('ReportArtifactService is not implemented.');
        }

        return new ReportArtifactService();
    }
}
