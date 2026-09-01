<?php

namespace Tests\Fixtures;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait CreatesExcelFixtures
{
    /**
     * @param  list<list<mixed>>  $rows
     */
    private function createWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueExplicit([$columnIndex + 1, $rowIndex + 1], $value, DataType::TYPE_STRING);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'labels-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
