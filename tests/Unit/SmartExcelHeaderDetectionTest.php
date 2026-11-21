<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\ExcelLabelParseException;
use App\Services\BarcodeLabels\ExcelLabelParser;
use App\Services\ManualCatalogue\ManualCatalogueExcelParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\CreatesExcelFixtures;
use Tests\TestCase;

final class SmartExcelHeaderDetectionTest extends TestCase
{
    use CreatesExcelFixtures;

    public function test_headers_are_found_in_second_row_and_include_zone(): void
    {
        $file = $this->createWorkbook([
            ['', '', '', ''],
            ['', 'Code ar.', 'Désignation', 'Zone'],
            ['', '6ADL53625001', 'Patin usure', 'E0011'],
        ]);

        $this->assertSame(['Code ar.', 'Désignation', 'Zone'], (new ExcelLabelParser)->headers($file));
        $labels = (new ExcelLabelParser)->parse($file);
        $this->assertCount(1, $labels);
        $this->assertSame('6ADL53625001&E0011', $labels[0]->qrPayload());

        $catalogue = (new ManualCatalogueExcelParser)->parse($file);
        $this->assertCount(1, $catalogue);
        $this->assertSame('Patin usure', $catalogue[0]->designation);
        $this->assertSame('E0011', $catalogue[0]->emplacement);
    }

    public function test_header_can_start_in_c5_with_metadata_above(): void
    {
        $file = $this->createWorkbook([
            ['Fichier des pieces'],
            ['Edition novembre'],
            [],
            [],
            ['', '', 'Code Article', 'Désignation', 'Zone de stockage'],
            ['', '', '001-AAA', 'Roulement', 'F001'],
            [],
            ['', '', '002-BBB', 'Vis', 'G002'],
        ]);

        $this->assertSame(['Code Article', 'Désignation', 'Zone de stockage'], (new ExcelLabelParser)->headers($file));
        $labels = (new ExcelLabelParser)->parse($file);
        $this->assertSame(['001-AAA&F001', '002-BBB&G002'], array_map(fn ($label) => $label->qrPayload(), $labels));
        $catalogue = (new ManualCatalogueExcelParser)->parse($file);
        $this->assertCount(2, $catalogue);
        $this->assertSame('Roulement', $catalogue[0]->designation);
    }

    #[DataProvider('validLocations')]
    public function test_location_aliases_work(string $header): void
    {
        $file = $this->createWorkbook([
            ['Code Article', 'Désignation', $header],
            ['001-AAA', 'Vis', 'a011'],
        ]);
        $this->assertSame('001-AAA&A011', (new ExcelLabelParser)->parse($file)[0]->qrPayload());
        $this->assertSame('a011', (new ManualCatalogueExcelParser)->parse($file)[0]->emplacement);
    }

    public static function validLocations(): array
    {
        return [
            'zone' => ['Zone'],
            'zone stockage' => ['Zone stockage'],
            'emplacement' => ['Emplacement'],
            'emp' => ['EMP'],
            'localisation' => ['Localisation'],
        ];
    }

    public function test_duplicates_are_not_silently_resolved(): void
    {
        $this->expectException(ExcelLabelParseException::class);
        $this->expectExceptionMessage('Plusieurs colonnes d emplacement');

        (new ExcelLabelParser)->parse($this->createWorkbook([
            ['Code article', 'Zone', 'Emplacement'],
            ['001-AAA', 'F001', 'F002'],
        ]));
    }

    public function test_generic_tracking_column_still_works(): void
    {
        $file = $this->createWorkbook([
            ['Tracking'],
            ['0007-AB'],
        ]);
        $this->assertSame('0007-AB', (new ExcelLabelParser)->parse($file, 'Tracking')[0]->code);
    }
}
