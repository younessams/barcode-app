<?php

namespace App\Services;

use App\Models\InventorySession;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class InventoryExcelExporter
{
    public function export(InventorySession $session): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Code Article');
        $sheet->setCellValue('B1', 'Quantité');
        $sheet->setCellValue('C1', 'QR Code');
        $tempFiles = [];

        try {
            foreach ($session->items()->orderBy('id')->get() as $row => $item) {
                $excelRow = $row + 2;
                $sheet->setCellValueExplicit('A'.$excelRow, $item->code_article, DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$excelRow, $item->quantity);
                $qrPath = $this->createQrPng($item->code_article, $session->uuid.'-'.$item->uuid);
                $tempFiles[] = $qrPath;
                $drawing = new Drawing;
                $drawing->setName('QR Code');
                $drawing->setDescription($item->code_article);
                $drawing->setPath($qrPath);
                $drawing->setHeight(72);
                $drawing->setCoordinates('C'.$excelRow);
                $drawing->setWorksheet($sheet);
                $sheet->getRowDimension($excelRow)->setRowHeight(58);
            }

            $sheet->getColumnDimension('A')->setWidth(26);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(14);
            $path = storage_path('app/'.'inventory-'.($session->uuid).'.xlsx');
            (new Xlsx($spreadsheet))->save($path);

            return $path;
        } finally {
            $spreadsheet->disconnectWorksheets();
            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile)) {
                    unlink($tempFile);
                }
            }
        }
    }

    private function createQrPng(string $value, string $key): string
    {
        require_once base_path('vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php');
        $data = (new \TCPDF2DBarcode($value, 'QRCODE,M'))->getBarcodeArray();
        $modules = (int) $data['num_rows'];
        $scale = 10;
        $quiet = 4;
        $image = \imagecreatetruecolor(($modules + ($quiet * 2)) * $scale, ($modules + ($quiet * 2)) * $scale);
        $white = \imagecolorallocate($image, 255, 255, 255);
        $black = \imagecolorallocate($image, 0, 0, 0);
        \imagefill($image, 0, 0, $white);
        foreach ($data['bcode'] as $row => $line) {
            foreach ($line as $column => $enabled) {
                if ($enabled) {
                    \imagefilledrectangle($image, ($column + $quiet) * $scale, ($row + $quiet) * $scale, (($column + $quiet + 1) * $scale) - 1, (($row + $quiet + 1) * $scale) - 1, $black);
                }
            }
        }

        $directory = storage_path('app/inventory-qr');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.'/'.hash('sha256', $key).'.png';
        // Lossless compression keeps every QR module unchanged while avoiding oversized workbook media.
        \imagepng($image, $path, 6);
        \imagedestroy($image);

        return $path;
    }
}
