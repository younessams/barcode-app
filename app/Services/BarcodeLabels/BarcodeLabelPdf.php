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
        $pdf->SetTitle('Barcode Labels');
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
            $qr = (new QrCodeLayout)->calculate(
                $label->qrPayload(),
                $layout,
                $slotIndex,
                $label->emplacement !== null,
            );
            $qrStyle = array_merge($style, ['hpadding' => QrCodeLayout::QUIET_ZONE_MODULES, 'vpadding' => QrCodeLayout::QUIET_ZONE_MODULES, 'module_width' => 1, 'module_height' => 1]);
            $pdf->write2DBarcode($label->qrPayload(), QrCodeLayout::ERROR_CORRECTION, $qr['xMm'], $qr['yMm'], $qr['totalSizeMm'], $qr['totalSizeMm'], $qrStyle, 'N', false);
            $textX = $qr['textXMm'];
            $textY = ($qr['horizontal'] ?? false) ? $qr['textYMm'] : $qr['yMm'] + $qr['totalSizeMm'] + $qr['textGapMm'];
            $textWidth = ($qr['horizontal'] ?? false) ? $qr['textWidthMm'] : $layout['guides']['labelWidthMm'];

            $this->drawQrText(
                $pdf,
                $label,
                $qr,
                $textX,
                $textY,
                $textWidth,
            );

            return;
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

    /** @param array<string, mixed> $qr */
    private function drawQrText(
        TCPDF $pdf,
        BarcodeLabel $label,
        array $qr,
        float $textX,
        float $textY,
        float $textWidth,
    ): void {
        $codeFont = $this->fitTextFont(
            $pdf,
            $label->codeArticle,
            $textWidth,
            (float) $qr['textFontPt'],
        );

        $pdf->SetFont('helvetica', 'B', $codeFont);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($textX, $textY);
        $pdf->Cell(
            $textWidth,
            $qr['codeTextHeightMm'],
            $label->codeArticle,
            0,
            0,
            'C',
            false,
            '',
            1,
            false,
            'T',
            'M',
        );

        if ($label->emplacement === null) {
            return;
        }

        if (($qr['horizontal'] ?? false) === true) {
            $emplacementBox = (new QrCodeLayout)->emplacementBox($pdf, $label->emplacement, $qr);
            $pdf->SetFont('helvetica', 'B', $emplacementBox['fontPt']);
            $pdf->SetLineStyle(['width' => 0.16, 'cap' => 'butt', 'join' => 'round', 'dash' => '1,1', 'color' => [56, 56, 56]]);
            $pdf->RoundedRect(
                $emplacementBox['xMm'],
                $emplacementBox['yMm'],
                $emplacementBox['widthMm'],
                $emplacementBox['heightMm'],
                $qr['emplacementBoxRadiusMm'],
                '1111',
                'D',
            );
            $pdf->SetLineStyle(['width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [0, 0, 0]]);
            $pdf->SetXY($emplacementBox['xMm'], $emplacementBox['yMm']);
            $pdf->Cell(
                $emplacementBox['widthMm'],
                $emplacementBox['heightMm'],
                $emplacementBox['text'],
                0,
                0,
                'C',
                false,
                '',
                1,
                false,
                'T',
                'M',
            );

            return;
        }

        $emplacementFont = $this->fitTextFont(
            $pdf,
            $label->emplacement,
            $textWidth,
            (float) $qr['emplacementTextFontPt'],
        );
        $pdf->SetFont('helvetica', 'B', $emplacementFont);
        $pdf->SetXY(
            $textX,
            $textY
            + $qr['codeTextHeightMm']
            + $qr['emplacementTextGapMm'],
        );
        $pdf->Cell(
            $textWidth,
            $qr['emplacementTextHeightMm'],
            $label->emplacement,
            0,
            0,
            'C',
            false,
            '',
            1,
            false,
            'T',
            'M',
        );
    }

    private function fitTextFont(
        TCPDF $pdf,
        string $text,
        float $widthMm,
        float $preferredSize,
    ): float {
        $minimumSize = min($preferredSize, 4.0);
        $size = $preferredSize;

        while ($size > $minimumSize) {
            $pdf->SetFont('helvetica', 'B', $size);

            if ($pdf->GetStringWidth($text) <= $widthMm) {
                break;
            }

            $size = round($size - 0.2, 1);
        }

        return max($minimumSize, $size);
    }
}
