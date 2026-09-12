<?php

namespace App\Services\ManualCatalogue;

use TCPDF;

final class ManualCataloguePdf
{
    public function __construct(
        private readonly ManualCatalogueLayout $layout = new ManualCatalogueLayout(),
    ) {
    }

    /**
     * @param list<ManualCatalogueItem> $items
     */
    public function render(array $items): string
    {
        $pdf = new TCPDF(
            'P',
            'mm',
            [
                ManualCatalogueLayout::PAGE_WIDTH_MM,
                ManualCatalogueLayout::PAGE_HEIGHT_MM,
            ],
            true,
            'UTF-8',
            false
        );

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $pdf->SetCreator('Barcode App');
        $pdf->SetAuthor('Internal');
        $pdf->SetTitle('Catalogue QR');

        $slots = $this->layout->slots();
        $slotsPerPage = $this->layout->slotsPerPage();

        foreach (array_chunk($items, $slotsPerPage) as $pageItems) {
            $pdf->AddPage(
                'P',
                [
                    ManualCatalogueLayout::PAGE_WIDTH_MM,
                    ManualCatalogueLayout::PAGE_HEIGHT_MM,
                ]
            );

            foreach ($pageItems as $index => $item) {
                $this->drawCard($pdf, $item, $slots[$index]);
            }
        }

        return $pdf->Output('catalogue-qr.pdf', 'S');
    }

    public function pageCount(int $itemCount): int
    {
        return max(
            1,
            (int) ceil($itemCount / $this->layout->slotsPerPage())
        );
    }

    /**
     * @param array{
     *   row:int,
     *   column:int,
     *   xMm:float,
     *   yMm:float,
     *   widthMm:float,
     *   heightMm:float
     * } $slot
     */
    private function drawCard(
        TCPDF $pdf,
        ManualCatalogueItem $item,
        array $slot,
    ): void {
        $x = $slot['xMm'];
        $y = $slot['yMm'];
        $width = $slot['widthMm'];
        $height = $slot['heightMm'];

        $padding = ManualCatalogueLayout::CARD_PADDING_MM;
        $qrSize = ManualCatalogueLayout::QR_SIZE_MM;

        /*
         * Card border
         */
        $pdf->SetDrawColor(199, 207, 214);
        $pdf->SetLineWidth(0.20);
        $pdf->RoundedRect(
            $x,
            $y,
            $width,
            $height,
            2.4,
            '1111',
            'D'
        );

        /*
         * QR area
         */
        $qrX = $x + $padding;
        $qrY = $y + (($height - $qrSize) / 2);

        $qrStyle = [
            'border' => false,
            'padding' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'module_width' => 1,
            'module_height' => 1,
        ];

        $pdf->write2DBarcode(
            $item->codeArticle,
            'QRCODE,M',
            $qrX,
            $qrY,
            $qrSize,
            $qrSize,
            $qrStyle,
            'N'
        );

        /*
         * Divider
         */
        $dividerX = $qrX + $qrSize + 3.6;

        $pdf->SetDrawColor(226, 231, 235);
        $pdf->SetLineWidth(0.15);
        $pdf->Line(
            $dividerX,
            $y + 4.0,
            $dividerX,
            $y + $height - 4.0
        );

        /*
         * Text zone
         */
        $textX = $dividerX + 4.0;
        $textRight = $x + $width - $padding;
        $textWidth = $textRight - $textX;

        /*
         * Code Article
         */
        $codeFont = $this->fitFont(
            $pdf,
            $item->codeArticle,
            $textWidth,
            9.4,
            7.4,
            'dejavusans',
            'B'
        );

        $pdf->SetTextColor(22, 32, 42);
        $pdf->SetFont('dejavusans', 'B', $codeFont);

        $pdf->SetXY(
            $textX,
            $y + 6.0
        );

        $pdf->Cell(
            $textWidth,
            6.0,
            $item->codeArticle,
            0,
            0,
            'C'
        );

        /*
         * Designation
         */
        $designationFont = 8.3;

        $lines = $this->designationLines(
            $pdf,
            $item->designation,
            $textWidth,
            $designationFont
        );

        $pdf->SetTextColor(75, 89, 100);
        $pdf->SetFont(
            'dejavusans',
            '',
            $designationFont
        );

        $designationHeight = count($lines) === 1
            ? 5.0
            : 9.0;

        $designationY = $y + 17.0;

        $pdf->SetXY(
            $textX,
            $designationY
        );

        $pdf->MultiCell(
            $textWidth,
            $designationHeight / count($lines),
            implode("\n", $lines),
            0,
            'C',
            false,
            1,
            $textX,
            $designationY,
            true,
            0,
            false,
            true,
            $designationHeight,
            'M'
        );

        /*
         * Emplacement badge
         */
        $badgeWidth = min(
            $textWidth,
            31.0
        );

        $badgeHeight = 8.2;

        $badgeX = $textX
            + (($textWidth - $badgeWidth) / 2);

        $badgeY = $y + $height - 13.6;

        $pdf->SetFillColor(243, 246, 248);
        $pdf->SetDrawColor(216, 224, 230);

        $pdf->RoundedRect(
            $badgeX,
            $badgeY,
            $badgeWidth,
            $badgeHeight,
            1.8,
            '1111',
            'DF'
        );

        $pdf->SetTextColor(106, 118, 128);
        $pdf->SetFont(
            'dejavusans',
            '',
            6.0
        );

        $pdf->SetXY(
            $badgeX,
            $badgeY + 0.5
        );

        $pdf->Cell(
            $badgeWidth,
            3.0,
            'EMPLACEMENT',
            0,
            0,
            'C'
        );

        $pdf->SetTextColor(23, 105, 170);
        $pdf->SetFont(
            'dejavusans',
            'B',
            9.0
        );

        $pdf->SetXY(
            $badgeX,
            $badgeY + 3.3
        );

        $pdf->Cell(
            $badgeWidth,
            4.0,
            $item->emplacement,
            0,
            0,
            'C'
        );
    }

    private function fitFont(
        TCPDF $pdf,
        string $text,
        float $maxWidth,
        float $startSize,
        float $minSize,
        string $font,
        string $style = '',
    ): float {
        $size = $startSize;

        while ($size > $minSize) {
            $pdf->SetFont($font, $style, $size);

            if ($pdf->GetStringWidth($text) <= $maxWidth) {
                break;
            }

            $size -= 0.2;
        }

        return round(
            max($size, $minSize),
            1
        );
    }

    /**
     * @return list<string>
     */
    private function designationLines(
        TCPDF $pdf,
        string $text,
        float $maxWidth,
        float $fontSize,
    ): array {
        $pdf->SetFont(
            'dejavusans',
            '',
            $fontSize
        );

        $words = preg_split(
            '/\s+/u',
            trim($text)
        ) ?: [];

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim(
                $current.' '.$word
            );

            if (
                $current === ''
                || $pdf->GetStringWidth($candidate) <= $maxWidth
            ) {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if ($lines === []) {
            return [''];
        }

        if (count($lines) <= 2) {
            return $lines;
        }

        $visible = [
            $lines[0],
            $lines[1],
        ];

        $last = $visible[1];

        while (
            $last !== ''
            && $pdf->GetStringWidth($last.'...') > $maxWidth
        ) {
            $last = mb_substr(
                $last,
                0,
                -1
            );
        }

        $visible[1] = rtrim($last).'...';

        return $visible;
    }
}
