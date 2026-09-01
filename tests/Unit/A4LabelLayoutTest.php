<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\A4LabelLayout;
use App\Services\BarcodeLabels\LabelLayoutException;
use Tests\TestCase;

final class A4LabelLayoutTest extends TestCase
{
    public function test_default_layout_keeps_existing_a4_three_by_eight_geometry(): void
    {
        $layout = (new A4LabelLayout)->default();
        $first = $layout['elements'][0];

        $this->assertSame(210.0, $layout['page']['widthMm']);
        $this->assertSame(297.0, $layout['page']['heightMm']);
        $this->assertSame(3, $layout['guides']['columns']);
        $this->assertSame(8, $layout['guides']['rows']);
        $this->assertSame(24, $layout['guides']['slotsPerPage']);
        $this->assertEqualsWithDelta(70.0, $layout['guides']['labelWidthMm'], 0.0001);
        $this->assertEqualsWithDelta(37.125, $layout['guides']['labelHeightMm'], 0.0001);
        $this->assertEqualsWithDelta(5.75, $first['xMm'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $first['yMm'], 0.0001);
        $this->assertEqualsWithDelta(58.5, $first['widthMm'], 0.0001);
        $this->assertEqualsWithDelta(28.4, $first['heightMm'], 0.0001);
    }

    public function test_gaps_and_margins_change_the_calculated_label_zone(): void
    {
        $layout = (new A4LabelLayout)->fromQuick([
            'columns' => 2,
            'rows' => 5,
            'gapXMm' => 4,
            'gapYMm' => 2,
            'marginTopMm' => 5,
            'marginRightMm' => 6,
            'marginBottomMm' => 7,
            'marginLeftMm' => 8,
        ]);

        $this->assertSame(10, $layout['guides']['slotsPerPage']);
        $this->assertEqualsWithDelta(96.0, $layout['guides']['labelWidthMm'], 0.0001);
        $this->assertEqualsWithDelta(55.4, $layout['guides']['labelHeightMm'], 0.0001);
        $this->assertEqualsWithDelta(13.75, $layout['elements'][0]['xMm'], 0.0001);
        $this->assertEqualsWithDelta(7.0, $layout['elements'][0]['yMm'], 0.0001);
    }

    public function test_impossible_quick_layout_is_rejected(): void
    {
        $this->expectException(LabelLayoutException::class);

        (new A4LabelLayout)->fromQuick([
            'columns' => 3,
            'rows' => 8,
            'gapXMm' => 200,
            'gapYMm' => 0,
            'marginTopMm' => 0,
            'marginRightMm' => 0,
            'marginBottomMm' => 0,
            'marginLeftMm' => 0,
        ]);
    }

    public function test_custom_elements_are_sorted_top_to_bottom_then_left_to_right(): void
    {
        $layout = (new A4LabelLayout)->normalize([
            'mode' => 'custom',
            'guides' => [
                'columns' => 3,
                'rows' => 8,
            ],
            'elements' => [
                ['id' => 'third', 'type' => 'barcode', 'xMm' => 90, 'yMm' => 40, 'widthMm' => 50, 'heightMm' => 22],
                ['id' => 'second', 'type' => 'barcode', 'xMm' => 80, 'yMm' => 4, 'widthMm' => 50, 'heightMm' => 22],
                ['id' => 'first', 'type' => 'barcode', 'xMm' => 5, 'yMm' => 4, 'widthMm' => 50, 'heightMm' => 22],
            ],
        ]);

        $this->assertSame(['first', 'second', 'third'], array_column($layout['elements'], 'id'));
    }

    public function test_unsafe_custom_geometry_is_rejected(): void
    {
        $this->expectException(LabelLayoutException::class);
        $this->expectExceptionMessage('trop petit');

        (new A4LabelLayout)->normalize([
            'mode' => 'custom',
            'guides' => ['columns' => 3, 'rows' => 8],
            'elements' => [
                ['id' => 'tiny', 'type' => 'barcode', 'xMm' => 5, 'yMm' => 5, 'widthMm' => 20, 'heightMm' => 10],
            ],
        ]);
    }

    public function test_custom_element_must_stay_inside_a4_with_text(): void
    {
        $this->expectException(LabelLayoutException::class);
        $this->expectExceptionMessage('hauteur A4');

        (new A4LabelLayout)->normalize([
            'mode' => 'custom',
            'guides' => ['columns' => 3, 'rows' => 8],
            'elements' => [
                ['id' => 'outside', 'type' => 'barcode', 'xMm' => 5, 'yMm' => 275, 'widthMm' => 50, 'heightMm' => 22],
            ],
        ]);
    }
}
