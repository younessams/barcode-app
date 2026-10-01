<?php

namespace App\Services\BarcodeLabels;

final class QrCodeLayout
{
    public const ERROR_CORRECTION = 'QRCODE,M';

    public const QUIET_ZONE_MODULES = 4;

    public const MIN_MODULE_MM = 0.40;

    public const RECOMMENDED_MODULE_MM = 0.50;

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
