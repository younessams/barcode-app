<?php

namespace App\Services\BarcodeLabels;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

final class ExcelLabelParser
{
    public const DEFAULT_COLUMN_NAME = 'Code Article';

    /**
     * @return list<BarcodeLabel>
     */
    public function parse(string $path, string $columnName = self::DEFAULT_COLUMN_NAME): array
    {
        $columnName = trim($columnName);
        if ($columnName === '') {
            throw new ExcelLabelParseException('Le nom de la colonne Excel est obligatoire.');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable) {
            throw new ExcelLabelParseException('Le fichier envoye ne peut pas etre lu comme un classeur Excel.');
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();
            $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            if ($highestRow < 2) {
                throw new ExcelLabelParseException('Le fichier Excel doit contenir une ligne d entete et au moins une ligne de donnees.');
            }

            $headers = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $headers[$column] = trim($this->cellString($sheet->getCell([$column, 1])));
            }

            $codeColumn = $this->detectCodeColumn($headers, $columnName);
            $labels = [];

            for ($row = 2; $row <= $highestRow; $row++) {
                if ($this->rowIsEmpty($sheet, $row, $highestColumnIndex)) {
                    continue;
                }

                $code = $this->cellString($sheet->getCell([$codeColumn, $row]));
                if ($code === '') {
                    continue;
                }

                $labels[] = new BarcodeLabel($code);
            }

            if ($labels === []) {
                throw new ExcelLabelParseException('Le fichier Excel ne contient aucune valeur non vide dans la colonne '.$columnName.'.');
            }

            return $labels;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public static function supportedAliases(): array
    {
        return [
            'code article' => [self::DEFAULT_COLUMN_NAME],
        ];
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function detectCodeColumn(array $headers, string $columnName): int
    {
        $columns = $this->matchingColumns($headers, $columnName);

        if (count($columns) > 1) {
            throw new ExcelLabelParseException('Plusieurs colonnes '.$columnName.' ont ete detectees. Conservez une seule colonne.');
        }

        if ($columns === []) {
            $availableHeaders = array_values(array_filter($headers, fn (string $header): bool => $header !== ''));
            $suffix = $availableHeaders === []
                ? ''
                : ' Colonnes disponibles : '.implode(', ', $availableHeaders).'.';

            throw new ExcelLabelParseException('Colonne "'.$columnName.'" introuvable dans le fichier Excel.'.$suffix);
        }

        return $columns[0];
    }

    /**
     * @param  array<int, string>  $headers
     * @return list<int>
     */
    private function matchingColumns(array $headers, string $columnName): array
    {
        $normalizedColumn = self::normalizeHeader($columnName);

        return array_values(array_keys(array_filter(
            $headers,
            fn (string $header): bool => self::normalizeHeader($header) === $normalizedColumn,
        )));
    }

    private static function normalizeHeader(string $header): string
    {
        return mb_strtolower(trim($header));
    }

    private function rowIsEmpty($sheet, int $row, int $highestColumnIndex): bool
    {
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            if ($this->cellString($sheet->getCell([$column, $row])) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cellString($cell): string
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            throw new ExcelLabelParseException('Les cellules avec formule ne sont pas acceptees. Collez les valeurs dans le fichier avant l import.');
        }

        $value = $cell->getValue();
        if ($value === null) {
            return '';
        }

        if (is_float($value) || is_int($value)) {
            $formatCode = $cell->getStyle()->getNumberFormat()->getFormatCode();
            if ($formatCode !== NumberFormat::FORMAT_GENERAL && str_contains($formatCode, '0')) {
                return trim((string) NumberFormat::toFormattedString($value, $formatCode));
            }

            if ((float) $value === floor((float) $value)) {
                return sprintf('%.0F', $value);
            }
        }

        return trim((string) $value);
    }
}
