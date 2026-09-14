<?php

namespace Tests\Unit;

use App\Support\CodeArticleNormalizer;
use Tests\TestCase;

final class CodeArticleNormalizerTest extends TestCase
{
    public function test_code_article_is_trimmed_and_uppercased_without_losing_code_characters(): void
    {
        $this->assertSame('6HYGSEC-009', CodeArticleNormalizer::normalize('6hygsec-009'));
        $this->assertSame('6HYGSEC-009', CodeArticleNormalizer::normalize('6HygSec-009'));
        $this->assertSame('6HYGSEC-009', CodeArticleNormalizer::normalize('  6HYGSEC-009  '));
        $this->assertSame('001AB-09', CodeArticleNormalizer::normalize('001ab-09'));
    }
}
