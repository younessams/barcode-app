<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\ExcelLabelParseException;
use App\Services\BarcodeLabels\ExcelLabelParser;
use Tests\Fixtures\CreatesExcelFixtures;
use Tests\TestCase;

final class ExcelLabelParserTest extends TestCase
{
    use CreatesExcelFixtures;

    public function test_code_article_header_is_detected(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            [' Code Article '],
            ['6DROGUER-050'],
        ]), 'code article');

        $this->assertCount(1, $labels);
        $this->assertSame('6DROGUER-050', $labels[0]->code);
    }

    public function test_requested_excel_column_is_used_without_guessing(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Reference', 'Stock Code'],
            ['REF-1', '001-ABC'],
        ]), ' stock code ');

        $this->assertCount(1, $labels);
        $this->assertSame('001-ABC', $labels[0]->code);
    }

    public function test_other_code_like_headers_are_not_guessed(): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('Colonne "Code Article" introuvable');
        $this->expectExceptionMessage('Colonnes disponibles : code_article.');

        (new ExcelLabelParser)->parse($this->createWorkbook([
            ['code_article'],
            ['6DROGUER-050'],
        ]), 'Code Article');
    }

    public function test_column_name_is_required(): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('Le nom de la colonne Excel est obligatoire');

        (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article'],
            ['6DROGUER-050'],
        ]), ' ');
    }

    public function test_leading_zeros_and_hyphenated_codes_are_preserved_exactly(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article'],
            ['001234'],
            ['6DROGUER-050'],
            [' ABC-001 '],
        ]));

        $this->assertSame(['001234', '6DROGUER-050', 'ABC-001'], array_map(
            fn ($label) => $label->code,
            $labels,
        ));
    }

    public function test_duplicate_excel_rows_remain_duplicates(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article'],
            ['6DROGUER-050'],
            ['6DROGUER-050'],
            ['6DROGUER-050'],
        ]));

        $this->assertCount(3, $labels);
        $this->assertSame(['6DROGUER-050', '6DROGUER-050', '6DROGUER-050'], array_map(
            fn ($label) => $label->code,
            $labels,
        ));
    }

    public function test_excel_row_order_is_preserved_and_empty_rows_are_ignored(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article'],
            ['6DROGUER-050'],
            [''],
            ['6DROGUER-052'],
            ['6DROGUER-083'],
            [''],
            ['6ACCES-950'],
        ]));

        $this->assertSame([
            '6DROGUER-050',
            '6DROGUER-052',
            '6DROGUER-083',
            '6ACCES-950',
        ], array_map(fn ($label) => $label->code, $labels));
    }

    public function test_requires_at_least_one_non_empty_code(): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('aucune valeur non vide');

        (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article', 'Designation'],
            ['', 'Article sans code'],
        ]));
    }

    public function test_one_thousand_rows_are_parsed_without_missing_or_unexpected_duplicates(): void
    {
        $rows = [['Code Article']];
        foreach (range(1, 1000) as $index) {
            $rows[] = [sprintf('CODE-%04d', $index)];
        }

        $labels = (new ExcelLabelParser)->parse($this->createWorkbook($rows));
        $codes = array_map(fn ($label) => $label->code, $labels);

        $this->assertCount(1000, $labels);
        $this->assertSame('CODE-0001', $codes[0]);
        $this->assertSame('CODE-0500', $codes[499]);
        $this->assertSame('CODE-1000', $codes[999]);
        $this->assertSame(array_values(array_unique($codes)), $codes);
    }
}
