<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\ExcelLabelParseException;
use App\Services\BarcodeLabels\ExcelLabelParser;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_generic_identifier_columns_are_supported(): void
    {
        foreach (['SKU', 'Tracking', 'Serial Number'] as $column) {
            $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
                ['Code Article', $column],
                ['WRONG', '0007-AB'],
                ['WRONG-2', '0008-AB'],
            ]), $column);

            $this->assertSame(['0007-AB', '0008-AB'], array_map(fn ($label) => $label->code, $labels));
        }
    }

    public function test_header_whitespace_is_normalized_without_fuzzy_matching(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ["Code\u{00A0}  Article"],
            ['0007'],
        ]), ' code article ');

        $this->assertSame('0007', $labels[0]->code);
    }

    public function test_unsupported_code_value_reports_row_and_value(): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('ligne Excel 2');
        $this->expectExceptionMessage('CAF');

        (new ExcelLabelParser)->parse($this->createWorkbook([
            ['SKU'],
            ['Café'],
        ]), 'SKU');
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
            ['6droguer-050'],
            [' abc-001 '],
        ]));

        $this->assertSame(['001234', '6DROGUER-050', 'ABC-001'], array_map(
            fn ($label) => $label->code,
            $labels,
        ));
    }

    public function test_qr_payloads_expose_separate_code_article_and_emplacement(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article'],
            [' 6roulement-086&a001 '],
            ['001ab-09'],
        ]));

        $this->assertSame('6ROULEMENT-086&A001', $labels[0]->qrPayload());
        $this->assertSame('6ROULEMENT-086', $labels[0]->codeArticle);
        $this->assertSame('A001', $labels[0]->emplacement);
        $this->assertSame('001AB-09', $labels[1]->qrPayload());
        $this->assertNull($labels[1]->emplacement);
    }

    #[DataProvider('emplacementHeaderAliases')]
    public function test_optional_emplacement_column_is_detected_by_exact_normalized_alias(string $header): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article', $header],
            [' 6visth-649 ', ' a0011 '],
        ]));

        $this->assertSame('6VISTH-649&A0011', $labels[0]->qrPayload());
        $this->assertSame('6VISTH-649', $labels[0]->codeArticle);
        $this->assertSame('A0011', $labels[0]->emplacement);
    }

    /** @return array<string, array{string}> */
    public static function emplacementHeaderAliases(): array
    {
        return [
            'canonical' => ['Emplacement'],
            'uppercase canonical' => ['EMPLACEMENT'],
            'lowercase canonical' => ['emplacement'],
            'short with whitespace' => [' Emp '],
            'uppercase short' => ['EMP'],
            'lowercase short' => ['emp'],
        ];
    }

    public function test_unrelated_columns_are_ignored_in_separate_column_mode(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Designation', 'Code Article', 'Stock', 'Emplacement', 'Fournisseur'],
            ['Vis acier', '6VISTH-649', '18', 'A0011', 'Supplier'],
        ]));

        $this->assertSame('6VISTH-649&A0011', $labels[0]->qrPayload());
    }

    public function test_blank_separate_emplacement_keeps_code_only_payload(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article', 'Emp'],
            ['VIS-125', ''],
        ]));

        $this->assertSame('VIS-125', $labels[0]->qrPayload());
        $this->assertNull($labels[0]->emplacement);
    }

    public function test_similar_header_is_not_fuzzily_matched_as_emplacement(): void
    {
        $labels = (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article', 'employee'],
            ['6VISTH-649&A0011', 'A0011'],
        ]));

        $this->assertSame('6VISTH-649&A0011', $labels[0]->qrPayload());
    }

    #[DataProvider('invalidSeparateColumnValues')]
    public function test_reserved_separator_in_separate_columns_reports_excel_row(
        string $codeArticle,
        string $emplacement,
        string $expectedMessage,
    ): void {
        try {
            (new ExcelLabelParser)->parse($this->createWorkbook([
                ['Code Article', 'Emplacement'],
                ['VALID-1', 'A001'],
                [$codeArticle, $emplacement],
            ]));
            $this->fail('Expected the separate-column row to be rejected.');
        } catch (ExcelLabelParseException $exception) {
            $this->assertStringContainsString('ligne Excel 3', $exception->getMessage());
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidSeparateColumnValues(): array
    {
        return [
            'matching composite code and emplacement' => ['6VISTH-649&A0011', 'A0011', 'Code Article contient le separateur reserve'],
            'conflicting composite code and emplacement' => ['6VISTH-649&A0011', 'B002', 'Code Article contient le separateur reserve'],
            'separator inside code' => ['6VISTH&649', 'A0011', 'Code Article contient le separateur reserve'],
            'separator inside emplacement' => ['6VISTH-649', 'A&0011', 'emplacement contient le separateur reserve'],
        ];
    }

    #[DataProvider('duplicateEmplacementHeaders')]
    public function test_duplicate_emplacement_columns_are_rejected(array $headers): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('Plusieurs colonnes d emplacement ont ete detectees');

        (new ExcelLabelParser)->parse($this->createWorkbook([
            $headers,
            ['CODE-1', 'A001', 'A002'],
        ]));
    }

    /** @return array<string, array{array<int, string>}> */
    public static function duplicateEmplacementHeaders(): array
    {
        return [
            'two aliases' => [['Code Article', 'Emp', 'Emplacement']],
            'normalized duplicate alias' => [['Code Article', ' EMP ', ' emp ']],
        ];
    }

    public function test_malformed_qr_payload_identifies_the_excel_row(): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('ligne Excel 2');
        $this->expectExceptionMessage('payload invalide');

        (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code Article'],
            ['CODE&A001&OTHER'],
        ]));
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

    public function test_blank_code_in_a_non_empty_row_is_rejected(): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('ligne Excel 2');
        $this->expectExceptionMessage('payload invalide');

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
