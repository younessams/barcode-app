<?php

namespace App\Services\BarcodeLabels;

final class A4LabelLayout
{
    public const PAGE_WIDTH_MM = 210.0;

    public const PAGE_HEIGHT_MM = 297.0;

    public const DEFAULT_COLUMNS = 3;

    public const DEFAULT_ROWS = 8;

    public const DEFAULT_GAP_X_MM = 0.0;

    public const DEFAULT_GAP_Y_MM = 0.0;

    public const DEFAULT_MARGIN_TOP_MM = 0.0;

    public const DEFAULT_MARGIN_RIGHT_MM = 0.0;

    public const DEFAULT_MARGIN_BOTTOM_MM = 0.0;

    public const DEFAULT_MARGIN_LEFT_MM = 0.0;

    public const DEFAULT_BARCODE_OFFSET_X_MM = 5.75;

    public const DEFAULT_BARCODE_OFFSET_Y_MM = 2.0;

    public const DEFAULT_BARCODE_WIDTH_MM = 58.5;

    public const DEFAULT_BARCODE_HEIGHT_MM = 28.4;

    public const CODE_TEXT_GAP_MM = 0.4;

    public const CODE_TEXT_HEIGHT_MM = 4.6;

    public const MIN_BARCODE_WIDTH_MM = 40.0;

    public const MIN_BARCODE_HEIGHT_MM = 18.0;

    private const DEFAULT_BOTTOM_SAFE_MM = 2.125;

    private const MAX_COLUMNS = 50;

    private const MAX_ROWS = 80;

    private const MAX_ELEMENTS = 400;

    /**
     * @return array{
     *     mode:string,
     *     page:array{widthMm:float,heightMm:float,orientation:string},
     *     guides:array{columns:int,rows:int,gapXMm:float,gapYMm:float,marginTopMm:float,marginRightMm:float,marginBottomMm:float,marginLeftMm:float,labelWidthMm:float,labelHeightMm:float,slotsPerPage:int},
     *     elements:list<array{id:string,type:string,xMm:float,yMm:float,widthMm:float,heightMm:float}>
     * }
     */
    public function default(): array
    {
        return $this->fromQuick([
            'columns' => self::DEFAULT_COLUMNS,
            'rows' => self::DEFAULT_ROWS,
            'gapXMm' => self::DEFAULT_GAP_X_MM,
            'gapYMm' => self::DEFAULT_GAP_Y_MM,
            'marginTopMm' => self::DEFAULT_MARGIN_TOP_MM,
            'marginRightMm' => self::DEFAULT_MARGIN_RIGHT_MM,
            'marginBottomMm' => self::DEFAULT_MARGIN_BOTTOM_MM,
            'marginLeftMm' => self::DEFAULT_MARGIN_LEFT_MM,
        ]);
    }

    /**
     * @param  array<string, mixed>|string  $layout
     * @return array<string, mixed>
     */
    public function normalize(array|string $layout): array
    {
        if (is_string($layout)) {
            try {
                $layout = json_decode($layout, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LabelLayoutException('La mise en page est invalide.');
            }
        }

        if (! is_array($layout)) {
            throw new LabelLayoutException('La mise en page est invalide.');
        }

        $mode = (string) ($layout['mode'] ?? 'quick');
        $guides = $this->normalizeGuides($layout['guides'] ?? []);
        $metrics = $this->calculateMetrics($guides);
        $guides = array_merge($guides, $metrics);

        $elements = $mode === 'quick'
            ? $this->buildElementsFromGuides($guides)
            : $this->normalizeElements($layout['elements'] ?? []);

        $normalized = [
            'mode' => $mode === 'custom' ? 'custom' : 'quick',
            'page' => [
                'widthMm' => self::PAGE_WIDTH_MM,
                'heightMm' => self::PAGE_HEIGHT_MM,
                'orientation' => 'portrait',
            ],
            'guides' => $guides,
            'elements' => $this->sortElements($elements),
        ];

        $this->assertUsable($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $guides
     * @return array<string, mixed>
     */
    public function fromQuick(array $guides): array
    {
        return $this->normalize([
            'mode' => 'quick',
            'page' => [
                'widthMm' => self::PAGE_WIDTH_MM,
                'heightMm' => self::PAGE_HEIGHT_MM,
                'orientation' => 'portrait',
            ],
            'guides' => $guides,
            'elements' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $guides
     * @return array{columns:int,rows:int,gapXMm:float,gapYMm:float,marginTopMm:float,marginRightMm:float,marginBottomMm:float,marginLeftMm:float}
     */
    private function normalizeGuides(array $guides): array
    {
        $columns = $this->integer($guides['columns'] ?? self::DEFAULT_COLUMNS, 'Colonnes');
        $rows = $this->integer($guides['rows'] ?? self::DEFAULT_ROWS, 'Lignes');

        if ($columns < 1 || $columns > self::MAX_COLUMNS) {
            throw new LabelLayoutException('Le nombre de colonnes est invalide.');
        }

        if ($rows < 1 || $rows > self::MAX_ROWS) {
            throw new LabelLayoutException('Le nombre de lignes est invalide.');
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'gapXMm' => $this->positiveFloat($guides['gapXMm'] ?? self::DEFAULT_GAP_X_MM, 'Espacement horizontal'),
            'gapYMm' => $this->positiveFloat($guides['gapYMm'] ?? self::DEFAULT_GAP_Y_MM, 'Espacement vertical'),
            'marginTopMm' => $this->positiveFloat($guides['marginTopMm'] ?? self::DEFAULT_MARGIN_TOP_MM, 'Marge haute'),
            'marginRightMm' => $this->positiveFloat($guides['marginRightMm'] ?? self::DEFAULT_MARGIN_RIGHT_MM, 'Marge droite'),
            'marginBottomMm' => $this->positiveFloat($guides['marginBottomMm'] ?? self::DEFAULT_MARGIN_BOTTOM_MM, 'Marge basse'),
            'marginLeftMm' => $this->positiveFloat($guides['marginLeftMm'] ?? self::DEFAULT_MARGIN_LEFT_MM, 'Marge gauche'),
        ];
    }

    /**
     * @param  array<string, mixed>  $guides
     * @return array{labelWidthMm:float,labelHeightMm:float,slotsPerPage:int}
     */
    private function calculateMetrics(array $guides): array
    {
        $availableWidth = self::PAGE_WIDTH_MM - $guides['marginLeftMm'] - $guides['marginRightMm'];
        $availableHeight = self::PAGE_HEIGHT_MM - $guides['marginTopMm'] - $guides['marginBottomMm'];
        $labelWidth = ($availableWidth - ($guides['gapXMm'] * ($guides['columns'] - 1))) / $guides['columns'];
        $labelHeight = ($availableHeight - ($guides['gapYMm'] * ($guides['rows'] - 1))) / $guides['rows'];

        if ($labelWidth <= 0) {
            throw new LabelLayoutException('La largeur calculee des etiquettes est invalide.');
        }

        if ($labelHeight <= 0) {
            throw new LabelLayoutException('La hauteur calculee des etiquettes est invalide.');
        }

        return [
            'labelWidthMm' => round($labelWidth, 4),
            'labelHeightMm' => round($labelHeight, 4),
            'slotsPerPage' => $guides['columns'] * $guides['rows'],
        ];
    }

    /**
     * @param  array<string, mixed>  $guides
     * @return list<array{id:string,type:string,xMm:float,yMm:float,widthMm:float,heightMm:float}>
     */
    private function buildElementsFromGuides(array $guides): array
    {
        $elements = [];
        $barcodeWidth = $guides['labelWidthMm'] - (self::DEFAULT_BARCODE_OFFSET_X_MM * 2);
        $barcodeHeight = $guides['labelHeightMm'] - self::DEFAULT_BARCODE_OFFSET_Y_MM - self::CODE_TEXT_HEIGHT_MM - self::DEFAULT_BOTTOM_SAFE_MM;

        for ($row = 0; $row < $guides['rows']; $row++) {
            for ($column = 0; $column < $guides['columns']; $column++) {
                $x = $guides['marginLeftMm'] + ($column * ($guides['labelWidthMm'] + $guides['gapXMm']));
                $y = $guides['marginTopMm'] + ($row * ($guides['labelHeightMm'] + $guides['gapYMm']));

                $elements[] = [
                    'id' => 'barcode-'.$row.'-'.$column,
                    'type' => 'barcode',
                    'xMm' => round($x + self::DEFAULT_BARCODE_OFFSET_X_MM, 4),
                    'yMm' => round($y + self::DEFAULT_BARCODE_OFFSET_Y_MM, 4),
                    'widthMm' => round($barcodeWidth, 4),
                    'heightMm' => round($barcodeHeight, 4),
                ];
            }
        }

        return $elements;
    }

    /**
     * @return list<array{id:string,type:string,xMm:float,yMm:float,widthMm:float,heightMm:float}>
     */
    private function normalizeElements(mixed $elements): array
    {
        if (! is_array($elements) || $elements === []) {
            throw new LabelLayoutException('La mise en page doit contenir au moins un code-barres.');
        }

        if (count($elements) > self::MAX_ELEMENTS) {
            throw new LabelLayoutException('La mise en page contient trop de codes-barres.');
        }

        $normalized = [];
        foreach (array_values($elements) as $index => $element) {
            if (! is_array($element)) {
                throw new LabelLayoutException('Un element de mise en page est invalide.');
            }

            if (($element['type'] ?? null) !== 'barcode') {
                throw new LabelLayoutException('Seuls les elements code-barres sont acceptes.');
            }

            $normalized[] = [
                'id' => (string) ($element['id'] ?? 'barcode-'.$index),
                'type' => 'barcode',
                'xMm' => round($this->positiveFloat($element['xMm'] ?? null, 'Position X'), 4),
                'yMm' => round($this->positiveFloat($element['yMm'] ?? null, 'Position Y'), 4),
                'widthMm' => round($this->positiveSize($element['widthMm'] ?? null, 'Largeur'), 4),
                'heightMm' => round($this->positiveSize($element['heightMm'] ?? null, 'Hauteur'), 4),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    private function assertUsable(array $layout): void
    {
        if (count($layout['elements']) < 1) {
            throw new LabelLayoutException('La mise en page doit contenir au moins un code-barres.');
        }

        foreach ($layout['elements'] as $element) {
            if ($element['widthMm'] < self::MIN_BARCODE_WIDTH_MM || $element['heightMm'] < self::MIN_BARCODE_HEIGHT_MM) {
                throw new LabelLayoutException('Un code-barres est trop petit pour une lecture fiable.');
            }

            if ($element['xMm'] + $element['widthMm'] > self::PAGE_WIDTH_MM) {
                throw new LabelLayoutException('Un code-barres depasse la largeur A4.');
            }

            if ($element['yMm'] + $element['heightMm'] + self::CODE_TEXT_GAP_MM + self::CODE_TEXT_HEIGHT_MM > self::PAGE_HEIGHT_MM) {
                throw new LabelLayoutException('Un code-barres depasse la hauteur A4.');
            }
        }
    }

    /**
     * @param  list<array{id:string,type:string,xMm:float,yMm:float,widthMm:float,heightMm:float}>  $elements
     * @return list<array{id:string,type:string,xMm:float,yMm:float,widthMm:float,heightMm:float}>
     */
    private function sortElements(array $elements): array
    {
        usort($elements, fn (array $first, array $second): int => [$first['yMm'], $first['xMm']] <=> [$second['yMm'], $second['xMm']]);

        return $elements;
    }

    private function integer(mixed $value, string $label): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new LabelLayoutException($label.' est invalide.');
        }

        return (int) $value;
    }

    private function positiveFloat(mixed $value, string $label): float
    {
        if (! is_numeric($value)) {
            throw new LabelLayoutException($label.' est invalide.');
        }

        $float = (float) $value;
        if (! is_finite($float) || $float < 0) {
            throw new LabelLayoutException($label.' est invalide.');
        }

        return $float;
    }

    private function positiveSize(mixed $value, string $label): float
    {
        $float = $this->positiveFloat($value, $label);
        if ($float <= 0) {
            throw new LabelLayoutException($label.' est invalide.');
        }

        return $float;
    }
}
