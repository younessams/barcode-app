<?php

namespace App\Services\BarcodeLabels;

use TCPDF;

final class QrCodeLayout
{
    public const ERROR_CORRECTION = 'QRCODE,M';

    public const QUIET_ZONE_MODULES = 4;

    public const MIN_MODULE_MM = 0.40;

    public const RECOMMENDED_MODULE_MM = 0.50;

    public const HORIZONTAL_QR_TARGET_MM = 21.8;

    public const HORIZONTAL_EMP_PADDING_X_MM = 2.0;

    public const HORIZONTAL_EMP_BOX_HEIGHT_MM = 5.5;

    public const HORIZONTAL_EMP_BOX_RADIUS_MM = 1.2;

    public const HORIZONTAL_EMP_VERTICAL_GAP_MM = 4.0;

    private const PRESET_70X37_TARGET_QR_MM = 24.0;

    private const PRESET_70X37_ROW_ALIGNMENT_MM = 0.125;

    private const PRESET_70X37_LOWER_ROW_LIFT_MM = 1.50;

    private const PRESET_70X37_TEXT_GAP_REDUCTION_MM = 0.15;

    private const PRESET_70X37_HORIZONTAL_OFFSET_MM = 0.00;

    private const PRESET_70X37_VERTICAL_DROP_MM = 1.00;

    private const PRESET_70X37_COLUMN_DROP_MM = 1.20;

    /** @return array{matrixModules:int, totalModules:int, moduleMm:float, totalSizeMm:float, xMm:float, yMm:float, textXMm:float, textFontPt:float, textGapMm:float, textHeightMm:float, codeTextHeightMm:float, emplacementTextFontPt:?float, emplacementTextGapMm:float, emplacementTextHeightMm:float, compact:bool} */
    public function calculate(
        string $value,
        array $layout,
        int $slotIndex,
        bool $hasEmplacement = false,
    ): array {
        $matrix = $this->matrix($value);
        $guides = $layout['guides'];
        $preset = $this->presetForSlot($guides, $slotIndex);
        $text = $this->textProfile($preset['labelHeightMm'], $hasEmplacement);
        if (($layout['presetId'] ?? null) === '52_5x29_7') {
            $totalModules = $matrix['modules'] + (2 * self::QUIET_ZONE_MODULES);

            return $this->calculateHorizontal52x297($matrix['modules'], $totalModules, $preset, $text, $hasEmplacement);
        }
        $safeHorizontalMm = 1.0;
        $safeTopMm = 0.5;
        $safeBottomMm = 0.5;
        $maxSizeMm = min(
            $preset['labelWidthMm'] - (2 * $safeHorizontalMm),
            $preset['labelHeightMm'] - $safeTopMm - $safeBottomMm - $text['heightMm'] - $text['gapMm'],
        );
        $totalModules = $matrix['modules'] + (2 * self::QUIET_ZONE_MODULES);
        $is70x37 = ($layout['presetId'] ?? null) === '70x37';

        if ($is70x37) {
            /*
             * Keep the common 70x37 QR visually compact (~24 mm), but never
             * force a dense QR below the recommended 0.50 mm module size when
             * the physical label has enough room to preserve that quality.
             */
            $recommendedSizeMm = $totalModules * self::RECOMMENDED_MODULE_MM;
            $maxSizeMm = min(
                $maxSizeMm,
                max(self::PRESET_70X37_TARGET_QR_MM, $recommendedSizeMm),
            );
        }

        $moduleMm = floor(($maxSizeMm / $totalModules) * 1000) / 1000;

        if ($moduleMm < self::MIN_MODULE_MM) {
            throw new QrCodeLayoutException('Ce QR Code est trop dense pour le format '.$preset['labelWidthMm'].' x '.$preset['labelHeightMm'].' mm. Choisissez un format plus grand ou utilisez Code 128.');
        }

        $totalSizeMm = round($moduleMm * $totalModules, 3);
        $row = intdiv($slotIndex, (int) $guides['columns']);
        $column = $slotIndex % (int) $guides['columns'];
        $rowAlignmentMm = $is70x37 ? ($row * self::PRESET_70X37_ROW_ALIGNMENT_MM) : 0.0;
        $lowerRowLiftMm = $is70x37 && $row > 0 ? self::PRESET_70X37_LOWER_ROW_LIFT_MM : 0.0;
        $textGapMm = $is70x37
            ? max(0.1, round($text['gapMm'] - self::PRESET_70X37_TEXT_GAP_REDUCTION_MM, 3))
            : $text['gapMm'];
        $horizontalOffsetMm = $is70x37 ? self::PRESET_70X37_HORIZONTAL_OFFSET_MM : 0.0;
        $verticalDropMm = $is70x37 ? self::PRESET_70X37_VERTICAL_DROP_MM : 0.0;
        $columnDropMm = $is70x37 ? ($column * self::PRESET_70X37_COLUMN_DROP_MM) : 0.0;

        return [
            'matrixModules' => $matrix['modules'],
            'totalModules' => $totalModules,
            'moduleMm' => $moduleMm,
            'totalSizeMm' => $totalSizeMm,
            'xMm' => round($preset['xMm'] + (($preset['labelWidthMm'] - $totalSizeMm) / 2) + $horizontalOffsetMm, 3),
            'yMm' => round($preset['yMm'] + $safeTopMm - $rowAlignmentMm - $lowerRowLiftMm + $verticalDropMm + $columnDropMm, 3),
            'textXMm' => round($preset['xMm'], 3),
            'textFontPt' => $text['codeFontPt'],
            'textGapMm' => $textGapMm,
            'textHeightMm' => $text['heightMm'],
            'codeTextHeightMm' => $text['codeHeightMm'],
            'emplacementTextFontPt' => $text['emplacementFontPt'],
            'emplacementTextGapMm' => $text['emplacementGapMm'],
            'emplacementTextHeightMm' => $text['emplacementHeightMm'],
            'compact' => $moduleMm < self::RECOMMENDED_MODULE_MM,
        ];
    }

    /** @return array<string, mixed> */
    private function calculateHorizontal52x297(
        int $matrixModules,
        int $totalModules,
        array $preset,
        array $text,
        bool $hasEmplacement,
    ): array {
        $safeMm = 2.0;
        $qrLeftMm = 2.8;
        $qrToTextGapMm = 2.4;
        $availableSizeMm = min($preset['labelWidthMm'] - (2 * $safeMm), $preset['labelHeightMm'] - (2 * $safeMm));
        $recommendedSizeMm = $totalModules * self::RECOMMENDED_MODULE_MM;
        $totalSizeMm = min($availableSizeMm, max(self::HORIZONTAL_QR_TARGET_MM, $recommendedSizeMm));
        $moduleMm = floor(($totalSizeMm / $totalModules) * 1000) / 1000;

        if ($moduleMm < self::MIN_MODULE_MM) {
            throw new QrCodeLayoutException('Ce QR Code est trop dense pour le format '.$preset['labelWidthMm'].' x '.$preset['labelHeightMm'].' mm. Choisissez un format plus grand ou utilisez Code 128.');
        }

        $totalSizeMm = round($moduleMm * $totalModules, 3);
        $codeTextHeightMm = 3.2;
        $textHeightMm = $hasEmplacement
            ? $codeTextHeightMm + self::HORIZONTAL_EMP_VERTICAL_GAP_MM + self::HORIZONTAL_EMP_BOX_HEIGHT_MM
            : $codeTextHeightMm;
        $localTextYMm = $hasEmplacement
            ? 9.0
            : ($preset['labelHeightMm'] - $codeTextHeightMm) / 2;
        $localTextXMm = $qrLeftMm + $totalSizeMm + $qrToTextGapMm;
        $textWidthMm = $preset['labelWidthMm'] - $localTextXMm - $safeMm;

        return [
            'matrixModules' => $matrixModules,
            'totalModules' => $totalModules,
            'moduleMm' => $moduleMm,
            'totalSizeMm' => $totalSizeMm,
            'slotOriginXMm' => round($preset['xMm'], 3),
            'slotOriginYMm' => round($preset['yMm'], 3),
            'xMm' => round($preset['xMm'] + $qrLeftMm, 3),
            'yMm' => round($preset['yMm'] + (($preset['labelHeightMm'] - $totalSizeMm) / 2), 3),
            'textXMm' => round($preset['xMm'] + $localTextXMm, 3),
            'textYMm' => round($preset['yMm'] + $localTextYMm, 3),
            'textWidthMm' => round($textWidthMm, 3),
            'textFontPt' => 6.4,
            'textGapMm' => $text['gapMm'],
            'textHeightMm' => $textHeightMm,
            'codeTextHeightMm' => $codeTextHeightMm,
            'emplacementTextFontPt' => $hasEmplacement ? 6.35 : null,
            'emplacementTextGapMm' => $hasEmplacement ? self::HORIZONTAL_EMP_VERTICAL_GAP_MM : 0.0,
            'emplacementTextHeightMm' => $hasEmplacement ? self::HORIZONTAL_EMP_BOX_HEIGHT_MM : 0.0,
            'emplacementBoxPaddingXMm' => self::HORIZONTAL_EMP_PADDING_X_MM,
            'emplacementBoxRadiusMm' => self::HORIZONTAL_EMP_BOX_RADIUS_MM,
            'emplacementBox' => $hasEmplacement,
            'horizontal' => true,
            'compact' => $moduleMm < self::RECOMMENDED_MODULE_MM,
        ];
    }

    /** @return array{modules:int} */
    public function matrix(string $value): array
    {
        require_once dirname(__DIR__, 3).'/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
        $barcode = new \TCPDF2DBarcode($value, self::ERROR_CORRECTION);
        $data = $barcode->getBarcodeArray();
        $rows = (int) ($data['num_rows'] ?? 0);
        $columns = (int) ($data['num_cols'] ?? 0);

        if ($rows <= 0 || $rows !== $columns) {
            throw new QrCodeLayoutException('La valeur ne peut pas etre encodee en QR Code.');
        }

        return ['modules' => $rows];
    }

    /** @return array{text:string,fontPt:float,xMm:float,yMm:float,widthMm:float,heightMm:float} */
    public function emplacementBox(TCPDF $pdf, string $emplacement, array $qr): array
    {
        $text = 'Emp: '.$emplacement;
        $paddingX = (float) $qr['emplacementBoxPaddingXMm'];
        $availableTextWidth = (float) $qr['textWidthMm'] - (2 * $paddingX);
        $fontPt = (float) $qr['emplacementTextFontPt'];
        $minimumFontPt = min($fontPt, 4.0);

        while ($fontPt > $minimumFontPt) {
            $pdf->SetFont('helvetica', 'B', $fontPt);

            if ($pdf->GetStringWidth($text) <= $availableTextWidth) {
                break;
            }

            $fontPt = round($fontPt - 0.2, 2);
        }

        $fontPt = max($minimumFontPt, $fontPt);
        $pdf->SetFont('helvetica', 'B', $fontPt);
        $widthMm = min(
            (float) $qr['textWidthMm'],
            $pdf->GetStringWidth($text) + (2 * $paddingX),
        );

        return [
            'text' => $text,
            'fontPt' => $fontPt,
            'xMm' => round((float) $qr['textXMm'] + (((float) $qr['textWidthMm'] - $widthMm) / 2), 3),
            'yMm' => round((float) $qr['textYMm'] + (float) $qr['codeTextHeightMm'] + (float) $qr['emplacementTextGapMm'], 3),
            'widthMm' => round($widthMm, 3),
            'heightMm' => (float) $qr['emplacementTextHeightMm'],
        ];
    }

    /** @return array{xMm:float,yMm:float,labelWidthMm:float,labelHeightMm:float} */
    private function presetForSlot(array $guides, int $slotIndex): array
    {
        $columns = (int) $guides['columns'];
        $row = intdiv($slotIndex, $columns);
        $column = $slotIndex % $columns;

        return [
            'xMm' => (float) $guides['marginLeftMm'] + ($column * ((float) $guides['labelWidthMm'] + (float) $guides['gapXMm'])),
            'yMm' => (float) $guides['marginTopMm'] + ($row * ((float) $guides['labelHeightMm'] + (float) $guides['gapYMm'])),
            'labelWidthMm' => (float) $guides['labelWidthMm'],
            'labelHeightMm' => (float) $guides['labelHeightMm'],
        ];
    }

    /** @return array{codeFontPt:float,gapMm:float,heightMm:float,codeHeightMm:float,emplacementFontPt:?float,emplacementGapMm:float,emplacementHeightMm:float} */
    private function textProfile(
        float $labelHeightMm,
        bool $hasEmplacement,
    ): array {
        if (! $hasEmplacement) {
            return match (true) {
                $labelHeightMm <= 21.2 => ['codeFontPt' => 5.6, 'gapMm' => 0.2, 'heightMm' => 3.2, 'codeHeightMm' => 3.2, 'emplacementFontPt' => null, 'emplacementGapMm' => 0.0, 'emplacementHeightMm' => 0.0],
                $labelHeightMm <= 29.7 => ['codeFontPt' => 6.5, 'gapMm' => 0.25, 'heightMm' => 4.0, 'codeHeightMm' => 4.0, 'emplacementFontPt' => null, 'emplacementGapMm' => 0.0, 'emplacementHeightMm' => 0.0],
                $labelHeightMm <= 37.125 => ['codeFontPt' => 7.8, 'gapMm' => 0.25, 'heightMm' => 4.6, 'codeHeightMm' => 4.6, 'emplacementFontPt' => null, 'emplacementGapMm' => 0.0, 'emplacementHeightMm' => 0.0],
                $labelHeightMm <= 74.0 => ['codeFontPt' => 8.2, 'gapMm' => 0.25, 'heightMm' => 4.8, 'codeHeightMm' => 4.8, 'emplacementFontPt' => null, 'emplacementGapMm' => 0.0, 'emplacementHeightMm' => 0.0],
                default => ['codeFontPt' => 8.5, 'gapMm' => 0.25, 'heightMm' => 5.0, 'codeHeightMm' => 5.0, 'emplacementFontPt' => null, 'emplacementGapMm' => 0.0, 'emplacementHeightMm' => 0.0],
            };
        }

        return match (true) {
            $labelHeightMm <= 21.2 => ['codeFontPt' => 4.4, 'gapMm' => 0.2, 'heightMm' => 5.3, 'codeHeightMm' => 2.2, 'emplacementFontPt' => 5.6, 'emplacementGapMm' => 0.1, 'emplacementHeightMm' => 3.0],
            $labelHeightMm <= 29.7 => ['codeFontPt' => 5.2, 'gapMm' => 0.25, 'heightMm' => 5.9, 'codeHeightMm' => 2.6, 'emplacementFontPt' => 6.8, 'emplacementGapMm' => 0.1, 'emplacementHeightMm' => 3.2],
            $labelHeightMm <= 37.125 => ['codeFontPt' => 6.2, 'gapMm' => 0.25, 'heightMm' => 7.3, 'codeHeightMm' => 3.2, 'emplacementFontPt' => 8.0, 'emplacementGapMm' => 0.1, 'emplacementHeightMm' => 4.0],
            $labelHeightMm <= 74.0 => ['codeFontPt' => 6.8, 'gapMm' => 0.25, 'heightMm' => 8.2, 'codeHeightMm' => 3.5, 'emplacementFontPt' => 8.8, 'emplacementGapMm' => 0.2, 'emplacementHeightMm' => 4.5],
            default => ['codeFontPt' => 7.0, 'gapMm' => 0.25, 'heightMm' => 8.6, 'codeHeightMm' => 3.7, 'emplacementFontPt' => 9.0, 'emplacementGapMm' => 0.2, 'emplacementHeightMm' => 4.7],
        };
    }
}
