<?php

namespace App\Models;

use App\Support\CodeArticleNormalizer;
use Illuminate\Database\Eloquent\Model;

final class ArticleReference extends Model
{
    protected $fillable = [
        'code_article',
        'designation',
        'emplacement',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $reference): void {
            $reference->code_article = CodeArticleNormalizer::normalize($reference->code_article);
            $reference->designation = self::blankToNull($reference->designation);
            $reference->emplacement = self::blankToNull($reference->emplacement);
        });
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
