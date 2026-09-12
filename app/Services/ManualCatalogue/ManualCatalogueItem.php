<?php

namespace App\Services\ManualCatalogue;

final readonly class ManualCatalogueItem
{
    public function __construct(
        public string $codeArticle,
        public string $designation,
        public string $emplacement,
    ) {
    }
}
