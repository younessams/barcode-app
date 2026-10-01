<?php

namespace App\Services;

use App\Support\CodeArticleNormalizer;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ClientInventoryExcelExporter
{
    private const MAX_ROWS = 10000;

    private const MAX_TEXT_LENGTH = 255;

    private const MAX_QUANTITY_UNITS = 4_294_967_295_000;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function export(array $payload): string
    {
        $items = $this->validatedItems($payload);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValueExplicit('A1', 'Code Article', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B1', 'Quantité', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C1', 'Emplacement', DataType::TYPE_STRING);

        foreach ($items as $index => $item) {
            $row = $index + 2;
            $sheet->setCellValueExplicit('A'.$row, $item['codeArticle'], DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, $item['quantityUnits'] / 1000);
            $sheet->setCellValueExplicit('C'.$row, $item['emplacement'] ?? '', DataType::TYPE_STRING);
        }

        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(22);

        $path = storage_path('app/inventory-export-'.bin2hex(random_bytes(16)).'.xlsx');

        try {
            (new Xlsx($spreadsheet))->save($path);

            return $path;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{codeArticle: string, emplacement: string|null, quantityUnits: int}>
     */
    private function validatedItems(array $payload): array
    {
        if (! isset($payload['items']) || ! is_array($payload['items'])) {
            throw new InventoryExportException('Le contenu de l inventaire est invalide.');
        }

        if (count($payload['items']) > self::MAX_ROWS) {
            throw new InventoryExportException('Le nombre maximum de lignes exportables est de '.self::MAX_ROWS.'.');
        }

        $identities = [];
        $items = [];

        foreach ($payload['items'] as $item) {
            if (! is_array($item)) {
                throw new InventoryExportException('Une ligne d inventaire est invalide.');
            }

            $unexpected = array_diff(array_keys($item), ['uuid', 'codeArticle', 'emplacement', 'quantity']);

            if ($unexpected !== [] || ! array_key_exists('codeArticle', $item) || ! array_key_exists('quantity', $item)) {
                throw new InventoryExportException('La structure d une ligne d inventaire est invalide.');
            }

            if (! is_string($item['codeArticle']) && ! is_int($item['codeArticle']) && ! is_float($item['codeArticle'])) {
                throw new InventoryExportException('Le Code Article est invalide.');
            }

            $codeArticle = CodeArticleNormalizer::normalize($item['codeArticle']);
            $emplacement = $this->normalizeNullableText($item['emplacement'] ?? null);

            if ($codeArticle === '' || mb_strlen($codeArticle) > self::MAX_TEXT_LENGTH) {
                throw new InventoryExportException('Le Code Article est obligatoire et trop long.');
            }

            if ($emplacement !== null && mb_strlen($emplacement) > self::MAX_TEXT_LENGTH) {
                throw new InventoryExportException('L emplacement est trop long.');
            }

            $identity = json_encode([$codeArticle, $emplacement], JSON_THROW_ON_ERROR);

            if (isset($identities[$identity])) {
                throw new InventoryExportException('Deux lignes utilisent le même Code Article et le même emplacement.');
            }

            $identities[$identity] = true;
            $items[] = [
                'codeArticle' => $codeArticle,
                'emplacement' => $emplacement,
                'quantityUnits' => $this->quantityToUnits($item['quantity']),
            ];
        }

        return $items;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InventoryExportException('Une valeur texte est invalide.');
        }

        $value = CodeArticleNormalizer::normalize($value);

        return $value === '' ? null : $value;
    }

    private function quantityToUnits(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InventoryExportException('La quantité est invalide.');
        }

        $quantity = str_replace(',', '.', trim((string) $value));

        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $quantity, $matches)) {
            throw new InventoryExportException('La quantité doit être positive ou nulle avec au maximum trois décimales.');
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($whole) > 10 || (strlen($whole) === 10 && strcmp($whole, '4294967295') > 0)) {
            throw new InventoryExportException('La quantité dépasse la limite autorisée.');
        }

        $decimal = str_pad($matches[2] ?? '', 3, '0');
        $units = ((int) $whole * 1000) + (int) $decimal;

        if ($units > self::MAX_QUANTITY_UNITS) {
            throw new InventoryExportException('La quantité dépasse la limite autorisée.');
        }

        return $units;
    }
}
