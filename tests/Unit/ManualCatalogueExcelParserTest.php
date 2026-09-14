<?php

namespace Tests\Unit;

use App\Services\ManualCatalogue\ManualCatalogueExcelParser;
use Tests\Fixtures\CreatesExcelFixtures;
use Tests\TestCase;

final class ManualCatalogueExcelParserTest extends TestCase
{
    use CreatesExcelFixtures;

    public function test_code_article_is_normalized_without_changing_designation_or_emplacement(): void
    {
        $items = (new ManualCatalogueExcelParser)->parse($this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            [' 001ab-09 ', 'Article Mixed', 'a-01'],
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('001AB-09', $items[0]->codeArticle);
        $this->assertSame('Article Mixed', $items[0]->designation);
        $this->assertSame('a-01', $items[0]->emplacement);
    }
}
