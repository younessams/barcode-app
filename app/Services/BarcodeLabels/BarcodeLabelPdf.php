<?php

namespace App\Services\BarcodeLabels;

use TCPDF;

final class BarcodeLabelPdf
{
    public const PAGE_WIDTH_MM = 210.0;

    public const PAGE_HEIGHT_MM = 297.0;

    /** @param list<BarcodeLabel> $labels */
    public function render(array $labels, array $layout): string
    {
        $elements = $layout['elements'];
        $slotsPerPage = count($elements);
        $pdf = new TCPDF('P', 'mm', [A4LabelPresetCatalog::PAGE_WIDTH_MM, A4LabelPresetCatalog::PAGE_HEIGHT_MM], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCreator('Laravel Barcode Labels');
        $pdf->SetAuthor('Internal');
        $pdf->SetTitle('Code 128 Labels');
        $pdf->setCellPaddings(0, 0, 0, 0);
        $style = ['position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => 'C', 'border' => false, 'hpadding' => 0, 'vpadding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false];

        foreach (array_chunk($labels, $slotsPerPage) as $pageLabels) {
            $pdf->AddPage('P', [A4LabelPresetCatalog::PAGE_WIDTH_MM, A4LabelPresetCatalog::PAGE_HEIGHT_MM]);
            foreach ($pageLabels as $index => $label) {
                if (isset($elements[$index])) {
                    $this->drawElement($pdf, $label, $elements[$index], $style);
                }
            }
        }

        return $pdf->Output('labels.pdf', 'S');
    }

    /** @param array<string, mixed> $layout */
    public function pageCount(int $labelCount, array $layout): int
    {
        return max(1, (int) ceil($labelCount / count($layout['elements'])));
    }

    /** @param array<string, mixed> $element @param array<string, mixed> $style */
    private function drawElement(TCPDF $pdf, BarcodeLabel $label, array $element, array $style): void
    {
        $pdf->write1DBarcode($label->code, 'C128', $element['xMm'], $element['yMm'], $element['widthMm'], $element['heightMm'], 0.4, $style, 'N');
        $pdf->SetFont('helvetica', 'B', $element['textFontPt']);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($element['xMm'], $element['yMm'] + $element['heightMm'] + $element['textGapMm']);
        $pdf->Cell($element['widthMm'], $element['textHeightMm'], $label->code, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    }
}
