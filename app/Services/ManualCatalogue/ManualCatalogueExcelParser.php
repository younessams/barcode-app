<?php

namespace App\Services\ManualCatalogue;

use App\Services\Excel\SmartExcelHeaderDetector;
use App\Support\CodeArticleNormalizer;
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

            $detected = (new SmartExcelHeaderDetector)->detect(
                $sheet,
                fn ($cell): string => $this->cellString($cell),
            );
            $headers = $detected['headers'];

            $codeColumn = $this->detectColumn(
                $headers,
                ['code article', 'codearticle', 'code art', 'code ar', 'code d article', 'code produit', 'code product', 'reference article', 'ref article', 'sku', 'stock code'],
                'Code Article'
            );

            $designationColumn = $this->detectColumn(
                $headers,
                ['designation', 'designation article', 'libelle', 'libelle article', 'description', 'description article', 'nom article'],
                'Designation'
            );

            $emplacementColumn = $this->detectColumn(
                $headers,
                ['emplacement', 'emp', 'zone', 'zone stockage', 'zone de stockage', 'localisation'],
                'Emplacement'
            );

            $items = [];

            for ($row = $detected['row'] + 1; $row <= $highestRow; $row++) {
                $code = CodeArticleNormalizer::normalize(
                    $this->cellString(
                        $sheet->getCell([$codeColumn, $row])
                    )
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
        return SmartExcelHeaderDetector::normalize($header);
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
