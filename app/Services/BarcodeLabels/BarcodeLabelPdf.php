<?php

namespace App\Services\BarcodeLabels;

use TCPDF;

final class BarcodeLabelPdf
{
    public const PAGE_WIDTH_MM = 210.0;

    public const PAGE_HEIGHT_MM = 297.0;

    /** @param list<BarcodeLabel> $labels */
    public function render(array $labels, array $layout, string $codeType = CodeType::CODE128): string
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
                    $this->drawElement($pdf, $label, $elements[$index], $style, $layout, $index, $codeType);
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

    /** @param array<string, mixed> $element @param array<string, mixed> $style @param array<string, mixed> $layout */
    private function drawElement(TCPDF $pdf, BarcodeLabel $label, array $element, array $style, array $layout, int $slotIndex, string $codeType): void
    {
        if ($codeType === CodeType::QR) {
            $qr = (new QrCodeLayout)->calculate($label->code, $layout, $slotIndex);
            $qrStyle = array_merge($style, ['hpadding' => QrCodeLayout::QUIET_ZONE_MODULES, 'vpadding' => QrCodeLayout::QUIET_ZONE_MODULES, 'module_width' => 1, 'module_height' => 1]);
            $pdf->write2DBarcode($label->code, QrCodeLayout::ERROR_CORRECTION, $qr['xMm'], $qr['yMm'], $qr['totalSizeMm'], $qr['totalSizeMm'], $qrStyle, 'N', false);
            $textX = $qr['textXMm'];
            $textY = $qr['yMm'] + $qr['totalSizeMm'] + $qr['textGapMm'];
            $textWidth = $layout['guides']['labelWidthMm'];
            $textFont = $qr['textFontPt'];
            $textHeight = $qr['textHeightMm'];
        } else {
            $pdf->write1DBarcode($label->code, 'C128', $element['xMm'], $element['yMm'], $element['widthMm'], $element['heightMm'], 0.4, $style, 'N');
            $textX = $element['xMm'];
            $textY = $element['yMm'] + $element['heightMm'] + $element['textGapMm'];
            $textWidth = $element['widthMm'];
            $textFont = $element['textFontPt'];
            $textHeight = $element['textHeightMm'];
        }

        $pdf->SetFont('helvetica', 'B', $textFont);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($textX, $textY);
        $pdf->Cell($textWidth, $textHeight, $label->code, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    }
}
