<?php

namespace App\Services\BarcodeLabels;

final readonly class BarcodeLabel
{
    public readonly string $payload;

    public readonly string $codeArticle;

    public function __construct(
        public string $code,
        ?string $codeArticle = null,
        public ?string $emplacement = null,
    ) {
        $this->payload = $code;
        $this->codeArticle = $codeArticle ?? $code;
    }

    public static function fromArticleQrPayload(ArticleQrPayload $payload): self
    {
        return new self(
            $payload->payload,
            $payload->codeArticle,
            $payload->emplacement,
        );
    }

    public function qrPayload(): string
    {
        return $this->payload;
    }
}
