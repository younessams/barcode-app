<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\A4LabelPresetCatalog;
use App\Services\BarcodeLabels\BarcodeLabel;
use App\Services\BarcodeLabels\BarcodeLabelPdf;
use Tests\TestCase;

final class BarcodeLabelPdfTest extends TestCase
{
    public function test_default_pdf_is_vector_a4_and_uses_locked_geometry(): void
    {
        $layout = (new A4LabelPresetCatalog)->layout('70x37');
        $element = $layout['elements'][0];
        $this->assertSame(210.0, BarcodeLabelPdf::PAGE_WIDTH_MM);
        $this->assertSame(297.0, BarcodeLabelPdf::PAGE_HEIGHT_MM);
        $this->assertCount(24, $layout['elements']);
        $this->assertSame(6.75, $element['xMm']);
        $this->assertSame(1.0, $element['yMm']);
        $this->assertSame(56.5, $element['widthMm']);
        $this->assertSame(27.2, $element['heightMm']);
        $content = (new BarcodeLabelPdf)->render([new BarcodeLabel('6DROGUER-050')], $layout);
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('/MediaBox [0.000000 0.000000 595.275591 841.889764]', $content);
        $this->assertStringNotContainsString('/Subtype /Image', $content);
    }

    public function test_pagination_uses_selected_preset_capacity(): void
    {
        $pdf = new BarcodeLabelPdf;
        $catalog = new A4LabelPresetCatalog;
        $this->assertSame(1, $pdf->pageCount(24, $catalog->layout('70x37')));
        $this->assertSame(2, $pdf->pageCount(25, $catalog->layout('70x37')));
        $this->assertSame(1, $pdf->pageCount(65, $catalog->layout('38x21_2')));
        $this->assertSame(1, $pdf->pageCount(21, $catalog->layout('70x42_3')));
    }

    public function test_large_batch_generates_without_images(): void
    {
        $labels = array_map(fn (int $i): BarcodeLabel => new BarcodeLabel('CODE-'.$i), range(1, 25));
        $content = (new BarcodeLabelPdf)->render($labels, (new A4LabelPresetCatalog)->layout('70x37'));
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringNotContainsString('/Subtype /Image', $content);
    }
}
