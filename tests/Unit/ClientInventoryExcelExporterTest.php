<?php

namespace Tests\Unit;

use App\Services\ClientInventoryExcelExporter;
use App\Services\InventoryExportException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class ClientInventoryExcelExporterTest extends TestCase
{
    public function test_export_preserves_order_strings_decimals_and_blank_emplacement(): void
    {
        $path = app(ClientInventoryExcelExporter::class)->export([
            'zone' => 'Zone A',
            'items' => [
                ['uuid' => 'one', 'codeArticle' => '000012345', 'emplacement' => 'b21', 'quantity' => '2.125'],
                ['uuid' => 'two', 'codeArticle' => '000012345', 'emplacement' => 'A11', 'quantity' => '3'],
                ['uuid' => 'three', 'codeArticle' => 'VIS-125', 'emplacement' => null, 'quantity' => '0.125'],
            ],
        ]);

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame(['Code Article', 'Quantité', 'Emplacement'], $sheet->rangeToArray('A1:C1')[0]);
        $this->assertSame('000012345', $sheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame(2.125, $sheet->getCell('B2')->getValue());
        $this->assertSame('B21', $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
        $this->assertSame('000012345', $sheet->getCell('A3')->getValue());
        $this->assertSame('A11', $sheet->getCell('C3')->getValue());
        $this->assertSame('VIS-125', $sheet->getCell('A4')->getValue());
        $this->assertNull($sheet->getCell('C4')->getValue());
        $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('C4')->getDataType());
        $this->assertSame(0.125, $sheet->getCell('B4')->getValue());

        unlink($path);
    }

    public function test_formula_like_text_stays_explicit_text(): void
    {
        $path = app(ClientInventoryExcelExporter::class)->export([
            'items' => [[
                'codeArticle' => '=HYPERLINK("https://example.com","click")',
                'emplacement' => '+A1',
                'quantity' => '1.000',
            ]],
        ]);

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('=HYPERLINK("HTTPS://EXAMPLE.COM","CLICK")', $sheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame('+A1', $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
        $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('A2')->getDataType());
        $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('C2')->getDataType());

        unlink($path);
    }

    public function test_malformed_duplicate_and_excessive_payloads_are_rejected(): void
    {
        $exporter = app(ClientInventoryExcelExporter::class);

        foreach ([
            ['items' => [['codeArticle' => '', 'quantity' => '1']]],
            ['items' => [['codeArticle' => 'A', 'quantity' => '-1']]],
            ['items' => [['codeArticle' => 'A', 'quantity' => '1.0000']]],
            ['items' => [['codeArticle' => 'A', 'emplacement' => 'X', 'quantity' => '1'], ['codeArticle' => 'a', 'emplacement' => 'x', 'quantity' => '2']]],
        ] as $payload) {
            try {
                $exporter->export($payload);
                $this->fail('Expected an invalid inventory payload.');
            } catch (InventoryExportException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $exporter->export(['items' => array_fill(0, 10001, ['codeArticle' => 'A', 'quantity' => '1'])]);
            $this->fail('Expected the row limit to be enforced.');
        } catch (InventoryExportException $exception) {
            $this->addToAssertionCount(1);
        }
    }
}
