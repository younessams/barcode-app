<?php

namespace App\Services\BarcodeLabels;

use TCPDF;

final class BarcodeLabelPdf
{
    public const PAGE_WIDTH_MM = 210.0;

    public const PAGE_HEIGHT_MM = 297.0;

    public const COLUMNS = 3;

    public const ROWS = 8;

    public const LABELS_PER_PAGE = self::COLUMNS * self::ROWS;

    public const LABEL_WIDTH_MM = self::PAGE_WIDTH_MM / self::COLUMNS;

    public const LABEL_HEIGHT_MM = self::PAGE_HEIGHT_MM / self::ROWS;

    /**
     * @param  list<BarcodeLabel>  $labels
     * @param  array<string, mixed>|null  $layout
     */
    public function render(array $labels, ?array $layout = null): string
    {
        $layout ??= (new A4LabelLayout)->default();
        $elements = array_values($layout['elements']);
        $slotsPerPage = count($elements);

        $pdf = new TCPDF('P', 'mm', [self::PAGE_WIDTH_MM, self::PAGE_HEIGHT_MM], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCreator('Laravel Barcode Labels');
        $pdf->SetAuthor('Internal');
        $pdf->SetTitle('Code 128 Labels');
        $pdf->setCellPaddings(0, 0, 0, 0);

        $style = [
            'position' => '',
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'cellfitalign' => 'C',
            'border' => false,
            'hpadding' => 0,
            'vpadding' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'text' => false,
        ];

        foreach (array_chunk($labels, $slotsPerPage) as $pageLabels) {
            $pdf->AddPage('P', [self::PAGE_WIDTH_MM, self::PAGE_HEIGHT_MM]);

            foreach ($pageLabels as $index => $label) {
                if (! isset($elements[$index])) {
                    continue;
                }

                $this->drawElement($pdf, $label, $elements[$index], $style);
            }
        }

        return $pdf->Output('labels.pdf', 'S');
    }

    /**
     * @return array{page:int, cell:int, row:int, column:int, x:float, y:float, width:float, height:float}
     */
    public function positionFor(int $index): array
    {
        $cell = $index % self::LABELS_PER_PAGE;
        $row = intdiv($cell, self::COLUMNS);
        $column = $cell % self::COLUMNS;

        return [
            'page' => intdiv($index, self::LABELS_PER_PAGE),
            'cell' => $cell,
            'row' => $row,
            'column' => $column,
            'x' => $column * self::LABEL_WIDTH_MM,
            'y' => $row * self::LABEL_HEIGHT_MM,
            'width' => self::LABEL_WIDTH_MM,
            'height' => self::LABEL_HEIGHT_MM,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $layout
     */
    public function pageCount(int $labelCount, ?array $layout = null): int
    {
        $labelsPerPage = $layout === null ? self::LABELS_PER_PAGE : count($layout['elements']);

        return max(1, (int) ceil($labelCount / $labelsPerPage));
    }

    /**
     * @param  array{id:string,type:string,xMm:float,yMm:float,widthMm:float,heightMm:float}  $element
     * @param  array<string, mixed>  $style
     */
    private function drawElement(TCPDF $pdf, BarcodeLabel $label, array $element, array $style): void
    {
        $pdf->write1DBarcode(
            $label->code,
            'C128',
            $element['xMm'],
            $element['yMm'],
            $element['widthMm'],
            $element['heightMm'],
            0.4,
            $style,
            'N'
        );

        $textX = max(0.0, $element['xMm'] - 4.25);
        $textWidth = min(self::PAGE_WIDTH_MM - $textX, $element['widthMm'] + 8.5);
        $codeY = $element['yMm'] + $element['heightMm'] + A4LabelLayout::CODE_TEXT_GAP_MM;

        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($textX, $codeY);
        $pdf->Cell($textWidth, A4LabelLayout::CODE_TEXT_HEIGHT_MM, $this->truncate($label->code, 34), 0, 0, 'C', false, '', 0, false, 'T', 'M');
    }

    private function truncate(?string $value, int $maxCharacters): string
    {
        $value ??= '';

        if (mb_strlen($value) <= $maxCharacters) {
            return $value;
        }

        return mb_substr($value, 0, $maxCharacters - 3).'...';
    }
}
