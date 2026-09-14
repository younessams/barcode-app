<?php

namespace Tests\Unit;

use App\Services\ArticleReferences\ArticleReferenceExcelParser;
use App\Services\ArticleReferences\ArticleReferenceParseException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ArticleReferenceExcelParserTest extends TestCase
{
    public function test_imports_unique_rows_and_preserves_leading_zero_codes(): void
    {
        $data = (new ArticleReferenceExcelParser)->parse($this->workbook([
            [' Code Article ', 'Designation', 'Emplacement'],
            [' 00123 ', ' Article A ', ' A-01 '],
            ['', '', ''],
            ['00045', '', 'B-02'],
        ]));

        $this->assertCount(2, $data->rows);
        $this->assertSame(1, $data->skippedBlankRows);
        $this->assertSame('00123', $data->rows[0]->codeArticle);
        $this->assertSame('Article A', $data->rows[0]->designation);
        $this->assertSame('A-01', $data->rows[0]->emplacement);
        $this->assertSame('00045', $data->rows[1]->codeArticle);
        $this->assertNull($data->rows[1]->designation);
        $this->assertSame('B-02', $data->rows[1]->emplacement);
    }

    public function test_duplicate_code_rows_use_last_row_wins(): void
    {
        $data = (new ArticleReferenceExcelParser)->parse($this->workbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['00123', 'Article A', 'A-01'],
            ['00123', 'Article A updated', 'B-04'],
        ]));

        $this->assertCount(1, $data->rows);
        $this->assertSame(1, $data->duplicateRows);
        $this->assertSame('Article A updated', $data->rows[0]->designation);
        $this->assertSame('B-04', $data->rows[0]->emplacement);
    }

    public function test_blank_code_is_rejected(): void
    {
        $this->expectException(ArticleReferenceParseException::class);
        $this->expectExceptionMessage('ligne Excel 2');

        (new ArticleReferenceExcelParser)->parse($this->workbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['', 'Article sans code', 'A-01'],
        ]));
    }

    public function test_formula_cells_are_rejected(): void
    {
        foreach ([1, 2, 3] as $formulaColumn) {
            try {
                (new ArticleReferenceExcelParser)->parse($this->formulaWorkbook($formulaColumn));
                $this->fail('Formula column '.$formulaColumn.' was accepted.');
            } catch (ArticleReferenceParseException $exception) {
                $this->assertStringContainsString('formule', $exception->getMessage());
            }
        }
    }

    public function test_missing_required_headers_are_rejected(): void
    {
        $this->expectException(ArticleReferenceParseException::class);
        $this->expectExceptionMessage('Colonne "Emplacement" introuvable');

        (new ArticleReferenceExcelParser)->parse($this->workbook([
            ['Code Article', 'Designation'],
            ['00123', 'Article A'],
        ]));
    }

    public function test_large_import_parses_one_thousand_rows(): void
    {
        $rows = [['Code Article', 'Designation', 'Emplacement']];

        foreach (range(1, 1000) as $index) {
            $rows[] = [sprintf('REF-%04d', $index), 'Article '.$index, 'A-'.$index];
        }

        $data = (new ArticleReferenceExcelParser)->parse($this->workbook($rows));

        $this->assertCount(1000, $data->rows);
        $this->assertSame('REF-0001', $data->rows[0]->codeArticle);
        $this->assertSame('REF-1000', $data->rows[999]->codeArticle);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function workbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, $rowIndex + 1],
                    $value,
                    DataType::TYPE_STRING
                );
            }
        }

        return $this->save($spreadsheet);
    }

    private function formulaWorkbook(int $formulaColumn): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Code Article', 'Designation', 'Emplacement'],
            ['00123', 'Article A', 'A-01'],
        ]);
        $sheet->setCellValue([$formulaColumn, 2], '=CONCAT("X","1")');

        return $this->save($spreadsheet);
    }

    private function save(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'article-references-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
