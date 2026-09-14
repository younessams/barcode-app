<?php

namespace App\Services\ArticleReferences;

final readonly class ArticleReferenceImportRow
{
    public function __construct(
        public string $codeArticle,
        public ?string $designation,
        public ?string $emplacement,
    ) {
    }
}
