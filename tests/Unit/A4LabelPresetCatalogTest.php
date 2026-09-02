<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\A4LabelPresetCatalog;
use App\Services\BarcodeLabels\LabelPresetException;
use Tests\TestCase;

final class A4LabelPresetCatalogTest extends TestCase
{
    public function test_catalog_contains_exactly_the_seven_supported_presets(): void
    {
        $catalog = new A4LabelPresetCatalog;
        $this->assertSame(['38x21_2', '52_5x29_7', '70x37', '70x42_3', '105x37', '105x74', '105x148'], $catalog->ids());
        $this->assertSame('70x37', $catalog->default()['id']);
    }

    public function test_presets_fit_a4_and_have_expected_capacity(): void
    {
        $catalog = new A4LabelPresetCatalog;
        $expected = ['38x21_2' => [5, 13, 65], '52_5x29_7' => [4, 10, 40], '70x37' => [3, 8, 24], '70x42_3' => [3, 7, 21], '105x37' => [2, 8, 16], '105x74' => [2, 4, 8], '105x148' => [2, 2, 4]];
        foreach ($expected as $id => [$columns, $rows, $capacity]) {
            $preset = $catalog->get($id);
            $this->assertSame($columns, $preset['columns']);
            $this->assertSame($rows, $preset['rows']);
            $this->assertSame($capacity, $preset['labelsPerSheet']);
            $layout = $catalog->layout($id);
            $this->assertCount($capacity, $layout['elements']);
            foreach ($layout['elements'] as $element) {
                $this->assertLessThanOrEqual(210, $element['xMm'] + $element['widthMm']);
                $this->assertLessThanOrEqual(297, $element['yMm'] + $element['heightMm'] + $element['textGapMm'] + $element['textHeightMm']);
            }
        }
    }

    public function test_default_geometry_is_locked(): void
    {
        $element = (new A4LabelPresetCatalog)->layout('70x37')['elements'][0];
        $this->assertSame(6.75, $element['xMm']);
        $this->assertSame(1.0, $element['yMm']);
        $this->assertSame(56.5, $element['widthMm']);
        $this->assertSame(27.2, $element['heightMm']);
        $this->assertSame(7.8, $element['textFontPt']);
        $this->assertSame(0.25, $element['textGapMm']);
    }

    public function test_unknown_or_custom_preset_is_rejected(): void
    {
        $this->expectException(LabelPresetException::class);
        (new A4LabelPresetCatalog)->get('custom');
    }
}
