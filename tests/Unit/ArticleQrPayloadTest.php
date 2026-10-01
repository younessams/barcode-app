<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\ArticleQrPayload;
use App\Services\BarcodeLabels\ArticleQrPayloadParseException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArticleQrPayloadTest extends TestCase
{
    public function test_payload_with_emplacement_is_parsed_and_normalized(): void
    {
        $payload = ArticleQrPayload::parse(' 6roulement-086&a11 ');

        $this->assertSame('6ROULEMENT-086&A11', $payload->payload);
        $this->assertSame('6ROULEMENT-086', $payload->codeArticle);
        $this->assertSame('A11', $payload->emplacement);
    }

    public function test_payload_without_emplacement_keeps_a_null_emplacement(): void
    {
        $payload = ArticleQrPayload::parse('VIS-125');

        $this->assertSame('VIS-125', $payload->payload);
        $this->assertSame('VIS-125', $payload->codeArticle);
        $this->assertNull($payload->emplacement);
    }

    public function test_leading_zeros_are_preserved(): void
    {
        $payload = ArticleQrPayload::parse(' 001ab-09&a001 ');

        $this->assertSame('001AB-09&A001', $payload->payload);
        $this->assertSame('001AB-09', $payload->codeArticle);
        $this->assertSame('A001', $payload->emplacement);
    }

    #[DataProvider('malformedPayloads')]
    public function test_malformed_payloads_are_rejected(string $value): void
    {
        $this->expectException(ArticleQrPayloadParseException::class);

        ArticleQrPayload::parse($value);
    }

    /** @return array<string, array{string}> */
    public static function malformedPayloads(): array
    {
        return [
            'missing code article' => ['&A001'],
            'missing emplacement' => ['CODE&'],
            'multiple separators' => ['CODE&A001&OTHER'],
            'whitespace only' => ['   '],
        ];
    }
}
