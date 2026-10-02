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
            return self::fromParts($value, null);
        }

        [$codeArticle, $emplacement] = explode('&', $value, 2);

        if (CodeArticleNormalizer::normalize($codeArticle) === '') {
            throw new ArticleQrPayloadParseException(
                'Le Code Article avant le separateur "&" est obligatoire.'
            );
        }

        if (CodeArticleNormalizer::normalize($emplacement) === '') {
            throw new ArticleQrPayloadParseException(
                'L emplacement apres le separateur "&" est obligatoire.'
            );
        }

        return self::fromParts($codeArticle, $emplacement);
    }

    public static function fromParts(string $codeArticle, ?string $emplacement): self
    {
        if (str_contains($codeArticle, '&')) {
            throw new ArticleQrPayloadParseException(
                'Le Code Article contient le separateur reserve "&" alors qu une colonne Emplacement est deja presente. Utilisez le Code Article sans emplacement dans cette colonne.'
            );
        }

        if ($emplacement !== null && str_contains($emplacement, '&')) {
            throw new ArticleQrPayloadParseException(
                'L emplacement contient le separateur reserve "&".'
            );
        }

        $codeArticle = CodeArticleNormalizer::normalize($codeArticle);
        $emplacement = CodeArticleNormalizer::normalize($emplacement);

        if ($codeArticle === '') {
            throw new ArticleQrPayloadParseException(
                'La valeur Code Article est obligatoire.'
            );
        }

        if ($emplacement === '') {
            $emplacement = null;
        }

        return new self(
            $emplacement === null ? $codeArticle : $codeArticle.'&'.$emplacement,
            $codeArticle,
            $emplacement,
        );
    }
}
