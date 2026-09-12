<?php

namespace App\Services\ManualCatalogue;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

final class ManualCatalogueExcelParser
{
    /**
     * @return list<ManualCatalogueItem>
     */
    public function parse(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable) {
            throw new ManualCatalogueParseException(
                'Le fichier envoye ne peut pas etre lu comme un classeur Excel.'
            );
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestDataRow();
            $highestColumnIndex = Coordinate::columnIndexFromString(
                $sheet->getHighestDataColumn()
            );

            if ($highestRow < 2) {
                throw new ManualCatalogueParseException(
                    'Le fichier Excel doit contenir une ligne d entete et au moins une ligne de donnees.'
                );
            }

            $headers = [];

            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $headers[$column] = $this->cellString(
                    $sheet->getCell([$column, 1])
                );
            }

            $codeColumn = $this->detectColumn(
                $headers,
                ['code article', 'codearticle'],
                'Code Article'
            );

            $designationColumn = $this->detectColumn(
                $headers,
                ['designation'],
                'Designation'
            );

            $emplacementColumn = $this->detectColumn(
                $headers,
                ['emplacement'],
                'Emplacement'
            );

            $items = [];

            for ($row = 2; $row <= $highestRow; $row++) {
                $code = $this->cellString(
                    $sheet->getCell([$codeColumn, $row])
                );

                $designation = $this->cellString(
                    $sheet->getCell([$designationColumn, $row])
                );

                $emplacement = $this->cellString(
                    $sheet->getCell([$emplacementColumn, $row])
                );

                if (
                    $code === ''
                    && $designation === ''
                    && $emplacement === ''
                ) {
                    continue;
                }

                if ($code === '') {
                    throw new ManualCatalogueParseException(
                        'La ligne Excel '.$row.' ne contient pas de Code Article.'
                    );
                }

                if ($designation === '') {
                    throw new ManualCatalogueParseException(
                        'La ligne Excel '.$row.' ne contient pas de Designation.'
                    );
                }

                if ($emplacement === '') {
                    throw new ManualCatalogueParseException(
                        'La ligne Excel '.$row.' ne contient pas d Emplacement.'
                    );
                }

                if (mb_strlen($code) > 120) {
                    throw new ManualCatalogueParseException(
                        'Le Code Article de la ligne '.$row.' est trop long.'
                    );
                }

                if (preg_match('/[\x00-\x1F\x7F]/u', $code) === 1) {
                    throw new ManualCatalogueParseException(
                        'Le Code Article de la ligne '.$row.' contient des caracteres invalides.'
                    );
                }

                $items[] = new ManualCatalogueItem(
                    codeArticle: $code,
                    designation: $designation,
                    emplacement: $emplacement,
                );
            }

            if ($items === []) {
                throw new ManualCatalogueParseException(
                    'Le fichier Excel ne contient aucun article exploitable.'
                );
            }

            return $items;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param array<int, string> $headers
     * @param list<string> $aliases
     */
    private function detectColumn(
        array $headers,
        array $aliases,
        string $label,
    ): int {
        $aliases = array_map(
            fn (string $alias): string => self::normalizeHeader($alias),
            $aliases
        );

        $matches = [];

        foreach ($headers as $column => $header) {
            if (in_array(self::normalizeHeader($header), $aliases, true)) {
                $matches[] = $column;
            }
        }

        if (count($matches) > 1) {
            throw new ManualCatalogueParseException(
                'Plusieurs colonnes '.$label.' ont ete detectees.'
            );
        }

        if ($matches === []) {
            $available = array_values(
                array_filter(
                    $headers,
                    fn (string $header): bool => trim($header) !== ''
                )
            );

            $suffix = $available === []
                ? ''
                : ' Colonnes disponibles : '.implode(', ', $available).'.';

            throw new ManualCatalogueParseException(
                'Colonne "'.$label.'" introuvable.'.$suffix
            );
        }

        return $matches[0];
    }

    private static function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header), 'UTF-8');

        $header = strtr($header, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'á' => 'a',
            'ã' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);

        $header = preg_replace('/[\p{Z}\s]+/u', ' ', $header) ?? $header;

        return trim($header);
    }

    private function cellString($cell): string
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            throw new ManualCatalogueParseException(
                'Les cellules avec formule ne sont pas acceptees. Collez les valeurs avant l import.'
            );
        }

        $value = $cell->getValue();

        if ($value === null) {
            return '';
        }

        if (is_float($value) || is_int($value)) {
            $formatCode = $cell
                ->getStyle()
                ->getNumberFormat()
                ->getFormatCode();

            if (
                $formatCode !== NumberFormat::FORMAT_GENERAL
                && str_contains($formatCode, '0')
            ) {
                return trim(
                    (string) NumberFormat::toFormattedString(
                        $value,
                        $formatCode
                    )
                );
            }

            if ((float) $value === floor((float) $value)) {
                return sprintf('%.0F', $value);
            }
        }

        return trim((string) $value);
    }
}
