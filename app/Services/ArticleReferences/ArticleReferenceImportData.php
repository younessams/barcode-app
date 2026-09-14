<?php

namespace App\Services\ArticleReferences;

final readonly class ArticleReferenceImportData
{
    /**
     * @param  list<ArticleReferenceImportRow>  $rows
     */
    public function __construct(
        public array $rows,
        public int $duplicateRows,
        public int $skippedBlankRows,
    ) {
    }
}
