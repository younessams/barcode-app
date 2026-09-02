<?php

namespace App\Services\BarcodeLabels;

final class A4LabelPresetCatalog
{
    public const PAGE_WIDTH_MM = 210.0;

    public const PAGE_HEIGHT_MM = 297.0;

    public const DEFAULT_ID = '70x37';

    /** @var array<string, array<string, mixed>> */
    private const PRESETS = [
        '38x21_2' => [
            'id' => '38x21_2', 'displayWidthMm' => 38.0, 'displayHeightMm' => 21.2,
            'labelWidthMm' => 38.0, 'labelHeightMm' => 21.2, 'columns' => 5, 'rows' => 13, 'labelsPerSheet' => 65,
            'marginLeftMm' => 10.0, 'marginTopMm' => 10.7, 'marginRightMm' => 10.0, 'marginBottomMm' => 10.7, 'gapXMm' => 0.0, 'gapYMm' => 0.0,
            'barcode' => ['xMm' => 3.0, 'yMm' => 0.75, 'widthMm' => 32.0, 'heightMm' => 13.5, 'textFontPt' => 5.6, 'textGapMm' => 0.2, 'textHeightMm' => 3.2],
            'default' => false, 'recommended' => false,
        ],
        '52_5x29_7' => [
            'id' => '52_5x29_7', 'displayWidthMm' => 52.5, 'displayHeightMm' => 29.7,
            'labelWidthMm' => 52.5, 'labelHeightMm' => 29.7, 'columns' => 4, 'rows' => 10, 'labelsPerSheet' => 40,
            'marginLeftMm' => 0.0, 'marginTopMm' => 0.0, 'marginRightMm' => 0.0, 'marginBottomMm' => 0.0, 'gapXMm' => 0.0, 'gapYMm' => 0.0,
            'barcode' => ['xMm' => 4.5, 'yMm' => 1.0, 'widthMm' => 43.5, 'heightMm' => 20.0, 'textFontPt' => 6.5, 'textGapMm' => 0.25, 'textHeightMm' => 4.0],
            'default' => false, 'recommended' => false,
        ],
        '70x37' => [
            'id' => '70x37', 'displayWidthMm' => 70.0, 'displayHeightMm' => 37.0,
            'labelWidthMm' => 70.0, 'labelHeightMm' => 37.125, 'columns' => 3, 'rows' => 8, 'labelsPerSheet' => 24,
            'marginLeftMm' => 0.0, 'marginTopMm' => 0.0, 'marginRightMm' => 0.0, 'marginBottomMm' => 0.0, 'gapXMm' => 0.0, 'gapYMm' => 0.0,
            'barcode' => ['xMm' => 6.75, 'yMm' => 1.0, 'widthMm' => 56.5, 'heightMm' => 27.2, 'textFontPt' => 7.8, 'textGapMm' => 0.25, 'textHeightMm' => 4.6],
            'default' => true, 'recommended' => true,
        ],
        '70x42_3' => [
            'id' => '70x42_3', 'displayWidthMm' => 70.0, 'displayHeightMm' => 42.3,
            'labelWidthMm' => 70.0, 'labelHeightMm' => 42.3, 'columns' => 3, 'rows' => 7, 'labelsPerSheet' => 21,
            'marginLeftMm' => 0.0, 'marginTopMm' => 0.45, 'marginRightMm' => 0.0, 'marginBottomMm' => 0.45, 'gapXMm' => 0.0, 'gapYMm' => 0.0,
            'barcode' => ['xMm' => 6.75, 'yMm' => 1.0, 'widthMm' => 56.5, 'heightMm' => 27.2, 'textFontPt' => 7.8, 'textGapMm' => 0.25, 'textHeightMm' => 4.6],
            'default' => false, 'recommended' => false,
        ],
        '105x37' => [
            'id' => '105x37', 'displayWidthMm' => 105.0, 'displayHeightMm' => 37.0,
            'labelWidthMm' => 105.0, 'labelHeightMm' => 37.0, 'columns' => 2, 'rows' => 8, 'labelsPerSheet' => 16,
            'marginLeftMm' => 0.0, 'marginTopMm' => 0.5, 'marginRightMm' => 0.0, 'marginBottomMm' => 0.5, 'gapXMm' => 0.0, 'gapYMm' => 0.0,
            'barcode' => ['xMm' => 8.0, 'yMm' => 1.0, 'widthMm' => 89.0, 'heightMm' => 27.2, 'textFontPt' => 7.8, 'textGapMm' => 0.25, 'textHeightMm' => 4.6],
            'default' => false, 'recommended' => false,
        ],
        '105x74' => [
            'id' => '105x74', 'displayWidthMm' => 105.0, 'displayHeightMm' => 74.0,
            'labelWidthMm' => 105.0, 'labelHeightMm' => 74.0, 'columns' => 2, 'rows' => 4, 'labelsPerSheet' => 8,
            'marginLeftMm' => 0.0, 'marginTopMm' => 0.5, 'marginRightMm' => 0.0, 'marginBottomMm' => 0.5, 'gapXMm' => 0.0, 'gapYMm' => 0.0,
            'barcode' => ['xMm' => 12.0, 'yMm' => 17.0, 'widthMm' => 81.0, 'heightMm' => 27.2, 'textFontPt' => 8.2, 'textGapMm' => 0.25, 'textHeightMm' => 4.8],
            'default' => false, 'recommended' => false,
        ],
        '105x148' => [
            'id' => '105x148', 'displayWidthMm' => 105.0, 'displayHeightMm' => 148.0,
            'labelWidthMm' => 105.0, 'labelHeightMm' => 148.0, 'columns' => 2, 'rows' => 2, 'labelsPerSheet' => 4,
            'marginLeftMm' => 0.0, 'marginTopMm' => 0.5, 'marginRightMm' => 0.0, 'marginBottomMm' => 0.5, 'gapXMm' => 0.0, 'gapYMm' => 0.0,
            'barcode' => ['xMm' => 12.0, 'yMm' => 55.0, 'widthMm' => 81.0, 'heightMm' => 35.0, 'textFontPt' => 8.5, 'textGapMm' => 0.25, 'textHeightMm' => 5.0],
            'default' => false, 'recommended' => false,
        ],
    ];

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_values(self::PRESETS);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys(self::PRESETS);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        if (! isset(self::PRESETS[$id])) {
            throw new LabelPresetException('Le format d\'etiquette selectionne est invalide.');
        }

        return self::PRESETS[$id];
    }

    /** @return array<string, mixed> */
    public function default(): array
    {
        return $this->get(self::DEFAULT_ID);
    }

    /** @return array<string, mixed> */
    public function layout(string $id): array
    {
        $preset = $this->get($id);
        $elements = [];
        $barcode = $preset['barcode'];

        for ($row = 0; $row < $preset['rows']; $row++) {
            for ($column = 0; $column < $preset['columns']; $column++) {
                $elements[] = [
                    'id' => 'barcode-'.$row.'-'.$column,
                    'type' => 'barcode',
                    'xMm' => round($preset['marginLeftMm'] + ($column * ($preset['labelWidthMm'] + $preset['gapXMm'])) + $barcode['xMm'], 4),
                    'yMm' => round($preset['marginTopMm'] + ($row * ($preset['labelHeightMm'] + $preset['gapYMm'])) + $barcode['yMm'], 4),
                    'widthMm' => $barcode['widthMm'],
                    'heightMm' => $barcode['heightMm'],
                    'textFontPt' => $barcode['textFontPt'],
                    'textGapMm' => $barcode['textGapMm'],
                    'textHeightMm' => $barcode['textHeightMm'],
                ];
            }
        }

        return [
            'presetId' => $id,
            'page' => ['widthMm' => self::PAGE_WIDTH_MM, 'heightMm' => self::PAGE_HEIGHT_MM, 'orientation' => 'portrait'],
            'guides' => ['columns' => $preset['columns'], 'rows' => $preset['rows'], 'gapXMm' => $preset['gapXMm'], 'gapYMm' => $preset['gapYMm'], 'marginTopMm' => $preset['marginTopMm'], 'marginRightMm' => $preset['marginRightMm'], 'marginBottomMm' => $preset['marginBottomMm'], 'marginLeftMm' => $preset['marginLeftMm'], 'labelWidthMm' => $preset['labelWidthMm'], 'labelHeightMm' => $preset['labelHeightMm'], 'slotsPerPage' => $preset['labelsPerSheet']],
            'elements' => $elements,
        ];
    }
}
