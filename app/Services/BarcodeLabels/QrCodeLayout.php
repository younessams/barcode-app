<?php

namespace App\Services\BarcodeLabels;

final class QrCodeLayout
{
    public const ERROR_CORRECTION = 'QRCODE,M';

    public const QUIET_ZONE_MODULES = 4;

    public const MIN_MODULE_MM = 0.40;

    public const RECOMMENDED_MODULE_MM = 0.50;

    private const PRESET_70X37_ROW_ALIGNMENT_MM = 0.125;

    private const PRESET_70X37_LOWER_ROW_LIFT_MM = 1.50;

    private const PRESET_70X37_TEXT_GAP_REDUCTION_MM = 0.15;

    /** @return array{matrixModules:int, totalModules:int, moduleMm:float, totalSizeMm:float, xMm:float, yMm:float, textXMm:float, textFontPt:float, textGapMm:float, textHeightMm:float, compact:bool} */
    public function calculate(string $value, array $layout, int $slotIndex): array
    {
        $matrix = $this->matrix($value);
        $guides = $layout['guides'];
        $preset = $this->presetForSlot($guides, $slotIndex);
        $text = $this->textProfile($preset['labelHeightMm']);
        $safeHorizontalMm = 1.0;
        $safeTopMm = 0.5;
        $safeBottomMm = 0.5;
        $maxSizeMm = min(
            $preset['labelWidthMm'] - (2 * $safeHorizontalMm),
            $preset['labelHeightMm'] - $safeTopMm - $safeBottomMm - $text['heightMm'] - $text['gapMm'],
        );
        $totalModules = $matrix['modules'] + (2 * self::QUIET_ZONE_MODULES);
        $moduleMm = floor(($maxSizeMm / $totalModules) * 1000) / 1000;

        if ($moduleMm < self::MIN_MODULE_MM) {
            throw new QrCodeLayoutException('Ce QR Code est trop dense pour le format '.$preset['labelWidthMm'].' x '.$preset['labelHeightMm'].' mm. Choisissez un format plus grand ou utilisez Code 128.');
        }

        $totalSizeMm = round($moduleMm * $totalModules, 3);
        $row = intdiv($slotIndex, (int) $guides['columns']);
        $is70x37 = ($layout['presetId'] ?? null) === '70x37';
        $rowAlignmentMm = $is70x37 ? ($row * self::PRESET_70X37_ROW_ALIGNMENT_MM) : 0.0;
        $lowerRowLiftMm = $is70x37 && $row > 0 ? self::PRESET_70X37_LOWER_ROW_LIFT_MM : 0.0;
        $textGapMm = $is70x37
            ? max(0.1, round($text['gapMm'] - self::PRESET_70X37_TEXT_GAP_REDUCTION_MM, 3))
            : $text['gapMm'];

        return [
            'matrixModules' => $matrix['modules'],
            'totalModules' => $totalModules,
            'moduleMm' => $moduleMm,
            'totalSizeMm' => $totalSizeMm,
            'xMm' => round($preset['xMm'] + (($preset['labelWidthMm'] - $totalSizeMm) / 2), 3),
            'yMm' => round($preset['yMm'] + $safeTopMm - $rowAlignmentMm - $lowerRowLiftMm, 3),
            'textXMm' => round($preset['xMm'], 3),
            'textFontPt' => $text['fontPt'],
            'textGapMm' => $textGapMm,
            'textHeightMm' => $text['heightMm'],
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

    /** @return array{fontPt:float,gapMm:float,heightMm:float} */
    private function textProfile(float $labelHeightMm): array
    {
        return match (true) {
            $labelHeightMm <= 21.2 => ['fontPt' => 5.6, 'gapMm' => 0.2, 'heightMm' => 3.2],
            $labelHeightMm <= 29.7 => ['fontPt' => 6.5, 'gapMm' => 0.25, 'heightMm' => 4.0],
            $labelHeightMm <= 37.125 => ['fontPt' => 7.8, 'gapMm' => 0.25, 'heightMm' => 4.6],
            $labelHeightMm <= 74.0 => ['fontPt' => 8.2, 'gapMm' => 0.25, 'heightMm' => 4.8],
            default => ['fontPt' => 8.5, 'gapMm' => 0.25, 'heightMm' => 5.0],
        };
    }
}
