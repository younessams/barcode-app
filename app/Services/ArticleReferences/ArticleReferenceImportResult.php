<?php

namespace App\Services\ArticleReferences;

final readonly class ArticleReferenceImportResult
{
    public function __construct(
        public int $total,
        public int $inserted,
        public int $updated,
        public int $duplicateRows,
        public int $skippedBlankRows,
    ) {
    }
}
