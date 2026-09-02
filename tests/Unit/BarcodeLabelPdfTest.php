<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\A4LabelLayout;
use App\Services\BarcodeLabels\BarcodeLabel;
use App\Services\BarcodeLabels\BarcodeLabelPdf;
use Tests\TestCase;

final class BarcodeLabelPdfTest extends TestCase
{
    public function test_label_sheet_geometry_is_exact_a4_three_by_eight(): void
    {
        $pdf = new BarcodeLabelPdf;

        $this->assertSame(210.0, BarcodeLabelPdf::PAGE_WIDTH_MM);
        $this->assertSame(297.0, BarcodeLabelPdf::PAGE_HEIGHT_MM);
        $this->assertSame(3, BarcodeLabelPdf::COLUMNS);
        $this->assertSame(8, BarcodeLabelPdf::ROWS);
        $this->assertSame(24, BarcodeLabelPdf::LABELS_PER_PAGE);
        $this->assertEqualsWithDelta(70.0, BarcodeLabelPdf::LABEL_WIDTH_MM, 0.0001);
        $this->assertEqualsWithDelta(37.125, BarcodeLabelPdf::LABEL_HEIGHT_MM, 0.0001);

        $first = $pdf->positionFor(0);
        $this->assertSame(['page' => 0, 'cell' => 0, 'row' => 0, 'column' => 0], array_intersect_key($first, array_flip(['page', 'cell', 'row', 'column'])));
        $this->assertEqualsWithDelta(0.0, $first['x'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $first['y'], 0.0001);

        $third = $pdf->positionFor(2);
        $this->assertSame(0, $third['row']);
        $this->assertSame(2, $third['column']);
        $this->assertEqualsWithDelta(140.0, $third['x'], 0.0001);

        $fourth = $pdf->positionFor(3);
        $this->assertSame(1, $fourth['row']);
        $this->assertSame(0, $fourth['column']);
        $this->assertEqualsWithDelta(37.125, $fourth['y'], 0.0001);

        $lastOnPage = $pdf->positionFor(23);
        $this->assertSame(7, $lastOnPage['row']);
        $this->assertSame(2, $lastOnPage['column']);
        $this->assertEqualsWithDelta(140.0, $lastOnPage['x'], 0.0001);
        $this->assertEqualsWithDelta(259.875, $lastOnPage['y'], 0.0001);

        $nextPage = $pdf->positionFor(24);
        $this->assertSame(['page' => 1, 'cell' => 0, 'row' => 0, 'column' => 0], array_intersect_key($nextPage, array_flip(['page', 'cell', 'row', 'column'])));
    }

    public function test_pagination_has_no_accidental_extra_page(): void
    {
        $pdf = new BarcodeLabelPdf;

        $this->assertSame(1, $pdf->pageCount(1));
        $this->assertSame(1, $pdf->pageCount(24));
        $this->assertSame(2, $pdf->pageCount(25));
        $this->assertSame(21, $pdf->pageCount(500));
        $this->assertSame(42, $pdf->pageCount(1000));
    }

    public function test_code_128_pdf_is_vector_a4_and_contains_no_images(): void
    {
        $labels = [
            new BarcodeLabel('6DROGUER-050'),
            new BarcodeLabel('6DROGUER-052'),
        ];

        $content = (new BarcodeLabelPdf)->render($labels);

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('/MediaBox [0.000000 0.000000 595.275591 841.889764]', $content);
        $this->assertStringNotContainsString('/Subtype /Image', $content);
        $this->assertStringContainsString('/Type /Page', $content);
    }

    public function test_large_label_batch_positions_are_not_missing_or_duplicated(): void
    {
        $pdf = new BarcodeLabelPdf;
        $positions = [];

        for ($i = 0; $i < 500; $i++) {
            $position = $pdf->positionFor($i);
            $key = $position['page'].'-'.$position['cell'];
            $positions[$key] = true;
        }

        $this->assertCount(500, $positions);
        $this->assertArrayHasKey('20-19', $positions);
        $this->assertSame(21, $pdf->pageCount(500));
    }

    public function test_twenty_five_codes_fill_page_one_then_one_cell_on_page_two(): void
    {
        $pdf = new BarcodeLabelPdf;

        $this->assertSame(0, $pdf->positionFor(23)['page']);
        $this->assertSame(23, $pdf->positionFor(23)['cell']);
        $this->assertSame(1, $pdf->positionFor(24)['page']);
        $this->assertSame(0, $pdf->positionFor(24)['cell']);
        $this->assertSame(2, $pdf->pageCount(25));
    }

    public function test_five_hundred_and_one_thousand_labels_generate_successfully(): void
    {
        $pdf = new BarcodeLabelPdf;

        $fiveHundred = array_map(fn (int $i): BarcodeLabel => new BarcodeLabel('CODE-'.$i), range(1, 500));
        $oneThousand = array_map(fn (int $i): BarcodeLabel => new BarcodeLabel('CODE-'.$i), range(1, 1000));

        $this->assertStringStartsWith('%PDF', $pdf->render($fiveHundred));
        $this->assertStringStartsWith('%PDF', $pdf->render($oneThousand));
    }

    public function test_custom_layout_keeps_vector_pdf_and_controls_pagination(): void
    {
        $layout = (new A4LabelLayout)->normalize([
            'mode' => 'custom',
            'guides' => ['columns' => 3, 'rows' => 8],
            'elements' => [
                ['id' => 'one', 'type' => 'barcode', 'xMm' => 6.75, 'yMm' => 2, 'widthMm' => 56.5, 'heightMm' => 27.2],
                ['id' => 'two', 'type' => 'barcode', 'xMm' => 76.75, 'yMm' => 2, 'widthMm' => 56.5, 'heightMm' => 27.2],
            ],
        ]);

        $labels = [
            new BarcodeLabel('CODE-1'),
            new BarcodeLabel('CODE-2'),
            new BarcodeLabel('CODE-3'),
        ];

        $content = (new BarcodeLabelPdf)->render($labels, $layout);

        $this->assertSame(2, (new BarcodeLabelPdf)->pageCount(3, $layout));
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('/MediaBox [0.000000 0.000000 595.275591 841.889764]', $content);
        $this->assertStringNotContainsString('/Subtype /Image', $content);
    }
}
