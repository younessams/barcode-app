<?php

namespace App\Support;

final class CodeArticleNormalizer
{
    public static function normalize(string|int|float|null $code): string
    {
        return mb_strtoupper(trim((string) $code), 'UTF-8');
    }
}
