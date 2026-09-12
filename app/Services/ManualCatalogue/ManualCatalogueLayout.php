<?php

namespace App\Services\ManualCatalogue;

final class ManualCatalogueLayout
{
    public const PAGE_WIDTH_MM = 210.0;
    public const PAGE_HEIGHT_MM = 297.0;

    public const COLUMNS = 2;
    public const ROWS = 6;

    public const MARGIN_X_MM = 6.5;
    public const MARGIN_Y_MM = 7.0;

    public const GAP_X_MM = 3.0;
    public const GAP_Y_MM = 2.2;

    public const QR_SIZE_MM = 27.5;
    public const CARD_PADDING_MM = 4.0;

    public function slotsPerPage(): int
    {
        return self::COLUMNS * self::ROWS;
    }

    public function cardWidthMm(): float
    {
        return (
            self::PAGE_WIDTH_MM
            - (2 * self::MARGIN_X_MM)
            - ((self::COLUMNS - 1) * self::GAP_X_MM)
        ) / self::COLUMNS;
    }

    public function cardHeightMm(): float
    {
        return (
            self::PAGE_HEIGHT_MM
            - (2 * self::MARGIN_Y_MM)
            - ((self::ROWS - 1) * self::GAP_Y_MM)
        ) / self::ROWS;
    }

    public function slots(): array
    {
        $slots = [];

        $width = $this->cardWidthMm();
        $height = $this->cardHeightMm();

        for ($row = 0; $row < self::ROWS; $row++) {
            for ($column = 0; $column < self::COLUMNS; $column++) {
                $slots[] = [
                    'row' => $row,
                    'column' => $column,
                    'xMm' => round(
                        self::MARGIN_X_MM
                        + ($column * ($width + self::GAP_X_MM)),
                        3
                    ),
                    'yMm' => round(
                        self::MARGIN_Y_MM
                        + ($row * ($height + self::GAP_Y_MM)),
                        3
                    ),
                    'widthMm' => round($width, 3),
                    'heightMm' => round($height, 3),
                ];
            }
        }

        return $slots;
    }
}
