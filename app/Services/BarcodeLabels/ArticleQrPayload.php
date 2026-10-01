<?php

namespace App\Services\BarcodeLabels;

use App\Support\CodeArticleNormalizer;

final readonly class ArticleQrPayload
{
    public function __construct(
        public string $payload,
        public string $codeArticle,
        public ?string $emplacement,
    ) {}

    public static function parse(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new ArticleQrPayloadParseException(
                'La valeur Code Article est obligatoire.'
            );
        }

        $separatorCount = substr_count($value, '&');

        if ($separatorCount > 1) {
            throw new ArticleQrPayloadParseException(
                'Le format QR ne peut contenir qu un seul separateur "&".'
            );
        }

        if ($separatorCount === 0) {
            $codeArticle = CodeArticleNormalizer::normalize($value);

            return new self($codeArticle, $codeArticle, null);
        }

        [$codeArticle, $emplacement] = explode('&', $value, 2);
        $codeArticle = CodeArticleNormalizer::normalize($codeArticle);
        $emplacement = CodeArticleNormalizer::normalize($emplacement);

        if ($codeArticle === '') {
            throw new ArticleQrPayloadParseException(
                'Le Code Article avant le separateur "&" est obligatoire.'
            );
        }

        if ($emplacement === '') {
            throw new ArticleQrPayloadParseException(
                'L emplacement apres le separateur "&" est obligatoire.'
            );
        }

        return new self(
            $codeArticle.'&'.$emplacement,
            $codeArticle,
            $emplacement,
        );
    }
}
