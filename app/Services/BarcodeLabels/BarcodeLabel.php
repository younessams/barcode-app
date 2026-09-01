<?php

namespace App\Services\BarcodeLabels;

final readonly class BarcodeLabel
{
    public function __construct(
        public string $code,
    ) {}
}
