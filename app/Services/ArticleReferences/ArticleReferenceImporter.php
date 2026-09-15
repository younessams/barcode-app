<?php

namespace App\Services\ArticleReferences;

use App\Models\ArticleReference;
use Illuminate\Support\Facades\DB;

final class ArticleReferenceImporter
{
    public function import(ArticleReferenceImportData $import): ArticleReferenceImportResult
    {
        return DB::transaction(fn (): ArticleReferenceImportResult => $this->upsert($import));
    }

    private function upsert(ArticleReferenceImportData $import): ArticleReferenceImportResult
    {
        $codes = array_map(
            fn ($row): string => $row->codeArticle,
            $import->rows
        );

        $existingCodes = [];

        foreach (array_chunk($codes, 500) as $codeChunk) {
            array_push(
                $existingCodes,
                ...ArticleReference::query()
                    ->whereIn('code_article', $codeChunk)
                    ->pluck('code_article')
                    ->all()
            );
        }

        $existingLookup = array_fill_keys($existingCodes, true);
        $now = now();
        $records = [];

        foreach ($import->rows as $row) {
            $records[] = [
                'code_article' => $row->codeArticle,
                'designation' => $row->designation,
                'emplacement' => $row->emplacement,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 100) as $chunk) {
            ArticleReference::upsert(
                $chunk,
                ['code_article'],
                ['designation', 'emplacement', 'updated_at']
            );
        }

        $updated = count($existingLookup);
        $total = count($records);

        return new ArticleReferenceImportResult(
            total: $total,
            inserted: $total - $updated,
            updated: $updated,
            duplicateRows: $import->duplicateRows,
            skippedBlankRows: $import->skippedBlankRows,
        );
    }
}
