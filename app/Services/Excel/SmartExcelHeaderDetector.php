<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Finds the most plausible Excel header row without assuming A1.
 * Limited to the first 50 data rows and the first 256 data columns.
 * This is deterministic alias matching, not guessing from article values.
 */
final class SmartExcelHeaderDetector
{
    private const MAX_HEADER_ROW = 50;
    private const MAX_HEADER_COLUMN = 256;

    private const CODE_HEADERS = [
        'code article', 'codearticle', 'code art', 'code ar',
        'code d article', 'code produit', 'code product',
        'reference article', 'ref article', 'sku', 'stock code',
    ];

    private const DESIGNATION_HEADERS = [
        'designation', 'designation article', 'libelle', 'libelle article',
        'description', 'description article', 'nom article',
    ];

    private const EMPLACEMENT_HEADERS = [
        'emplacement', 'emp', 'zone', 'zone stockage',
        'zone de stockage', 'localisation',
    ];

    private const GENERIC_HEADERS = [
        'reference', 'ref', 'tracking', 'serial number', 'n serie',
        'numero de serie', 'n commande', 'numero de commande',
        'quantite', 'stock', 'fournisseur',
    ];

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        ]);
        $value = preg_replace('/[\p{P}\p{S}_]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function isCodeHeader(string $value): bool
    {
        return in_array(self::normalize($value), self::CODE_HEADERS, true);
    }

    public static function isDesignationHeader(string $value): bool
    {
        return in_array(self::normalize($value), self::DESIGNATION_HEADERS, true);
    }

    public static function isEmplacementHeader(string $value): bool
    {
        return in_array(self::normalize($value), self::EMPLACEMENT_HEADERS, true);
    }

    /**
     * @param callable(mixed): string $cellText Existing parser's safe cell reader
     * @return array{row: int, headers: array<int, string>}
     */
    public function detect(Worksheet $sheet, callable $cellText, ?string $preferredColumn = null): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestDataColumn()
        );
        $maxRow = min(self::MAX_HEADER_ROW, $highestRow);
        $maxColumn = min(self::MAX_HEADER_COLUMN, $highestColumn);
        $preferred = $preferredColumn === null ? null : self::normalize($preferredColumn);
        $best = null;
        $bestScore = -1;

        for ($row = 1; $row <= $maxRow; $row++) {
            $headers = [];
            $nonEmpty = 0;
            $roles = [];
            $preferredFound = false;
            $genericFound = false;

            for ($column = 1; $column <= $maxColumn; $column++) {
                $header = $cellText($sheet->getCell([$column, $row]));
                $headers[$column] = $header;
                if ($header === '') {
                    continue;
                }

                $nonEmpty++;
                $key = self::normalize($header);
                if (self::isCodeHeader($header)) {
                    $roles['code'] = true;
                }
                if (self::isDesignationHeader($header)) {
                    $roles['designation'] = true;
                }
                if (self::isEmplacementHeader($header)) {
                    $roles['emplacement'] = true;
                }
                if (in_array($key, self::GENERIC_HEADERS, true)) {
                    $genericFound = true;
                }
                if ($preferred !== null && $key === $preferred) {
                    $preferredFound = true;
                }
            }

            if ($nonEmpty === 0) {
                continue;
            }

            // Strongly prefer named headers to arbitrary cell values.
            // A title in A1 cannot win against Code Article/Désignation/Zone in B2:D2.
            $score = count($roles) * 100
                + ($preferredFound ? 80 : 0)
                + ($genericFound ? 40 : 0)
                + min($nonEmpty, 20);

            if ($score > $bestScore) {
                $best = ['row' => $row, 'headers' => $headers];
                $bestScore = $score;
            }
        }

        // Preserve the previous headers endpoint behavior for entirely blank sheets:
        // show an empty selector rather than raising an unhandled 500 response.
        return $best ?? ['row' => 1, 'headers' => []];
    }
}
