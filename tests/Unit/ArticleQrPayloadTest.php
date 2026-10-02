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

    public function test_separate_parts_are_normalized_and_build_one_canonical_payload(): void
    {
        $payload = ArticleQrPayload::fromParts(' 001ab-09 ', ' a001 ');

        $this->assertSame('001AB-09&A001', $payload->payload);
        $this->assertSame('001AB-09', $payload->codeArticle);
        $this->assertSame('A001', $payload->emplacement);
    }

    public function test_blank_separate_emplacement_produces_no_trailing_separator(): void
    {
        $payload = ArticleQrPayload::fromParts('VIS-125', '   ');

        $this->assertSame('VIS-125', $payload->payload);
        $this->assertNull($payload->emplacement);
    }

    #[DataProvider('invalidSeparateParts')]
    public function test_reserved_separator_is_rejected_inside_separate_parts(
        string $codeArticle,
        ?string $emplacement,
        string $expectedMessage,
    ): void {
        $this->expectException(ArticleQrPayloadParseException::class);
        $this->expectExceptionMessage($expectedMessage);

        ArticleQrPayload::fromParts($codeArticle, $emplacement);
    }

    /** @return array<string, array{string, ?string, string}> */
    public static function invalidSeparateParts(): array
    {
        return [
            'separator in article code' => ['CODE&A001', 'A001', 'Code Article contient le separateur reserve'],
            'separator in emplacement' => ['CODE', 'A&001', 'emplacement contient le separateur reserve'],
        ];
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
