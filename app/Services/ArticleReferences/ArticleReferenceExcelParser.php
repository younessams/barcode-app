<?php

namespace App\Services\ArticleReferences;

use App\Support\CodeArticleNormalizer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

final class ArticleReferenceExcelParser
{
    public function parse(string $path): ArticleReferenceImportData
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable) {
            throw new ArticleReferenceParseException(
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
                throw new ArticleReferenceParseException(
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

            $rowsByCode = [];
            $duplicateRows = 0;
            $skippedBlankRows = 0;

            for ($row = 2; $row <= $highestRow; $row++) {
                $code = CodeArticleNormalizer::normalize(
                    $this->cellString($sheet->getCell([$codeColumn, $row]))
                );
                $designation = $this->cellString($sheet->getCell([$designationColumn, $row]));
                $emplacement = $this->cellString($sheet->getCell([$emplacementColumn, $row]));

                if ($code === '' && $designation === '' && $emplacement === '') {
                    $skippedBlankRows++;

                    continue;
                }

                if ($code === '') {
                    throw new ArticleReferenceParseException(
                        'La ligne Excel '.$row.' ne contient pas de Code Article.'
                    );
                }

                if (mb_strlen($code) > 255) {
                    throw new ArticleReferenceParseException(
                        'Le Code Article de la ligne '.$row.' est trop long.'
                    );
                }

                if (mb_strlen($designation) > 255) {
                    throw new ArticleReferenceParseException(
                        'La Designation de la ligne '.$row.' est trop longue.'
                    );
                }

                if (mb_strlen($emplacement) > 255) {
                    throw new ArticleReferenceParseException(
                        'L Emplacement de la ligne '.$row.' est trop long.'
                    );
                }

                if (array_key_exists($code, $rowsByCode)) {
                    $duplicateRows++;
                }

                $rowsByCode[$code] = new ArticleReferenceImportRow(
                    codeArticle: $code,
                    designation: $this->blankToNull($designation),
                    emplacement: $this->blankToNull($emplacement),
                );
            }

            if ($rowsByCode === []) {
                throw new ArticleReferenceParseException(
                    'Le fichier Excel ne contient aucune reference article exploitable.'
                );
            }

            return new ArticleReferenceImportData(
                rows: array_values($rowsByCode),
                duplicateRows: $duplicateRows,
                skippedBlankRows: $skippedBlankRows,
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  list<string>  $aliases
     */
    private function detectColumn(array $headers, array $aliases, string $label): int
    {
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
            throw new ArticleReferenceParseException(
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

            throw new ArticleReferenceParseException(
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
            throw new ArticleReferenceParseException(
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

    private function blankToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
