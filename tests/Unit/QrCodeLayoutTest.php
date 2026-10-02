<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\A4LabelPresetCatalog;
use App\Services\BarcodeLabels\QrCodeLayout;
use App\Services\BarcodeLabels\QrCodeLayoutException;
use TCPDF;
use Tests\TestCase;

final class QrCodeLayoutTest extends TestCase
{
    public function test_short_value_fits_all_presets_with_actual_matrix_and_quiet_zone(): void
    {
        $catalog = new A4LabelPresetCatalog;
        $calculator = new QrCodeLayout;
        foreach ($catalog->ids() as $id) {
            $layout = $catalog->layout($id);
            $qr = $calculator->calculate('6DROGUER-050', $layout, 0);
            $guides = $layout['guides'];
            $this->assertSame($qr['matrixModules'] + 8, $qr['totalModules']);
            $this->assertGreaterThanOrEqual(QrCodeLayout::MIN_MODULE_MM, $qr['moduleMm']);
            $this->assertGreaterThanOrEqual((float) $guides['marginLeftMm'], $qr['xMm']);
            $this->assertLessThanOrEqual((float) $guides['marginLeftMm'] + $guides['labelWidthMm'], $qr['xMm'] + $qr['totalSizeMm']);
            $this->assertLessThanOrEqual((float) $guides['marginTopMm'] + $guides['labelHeightMm'], $qr['yMm'] + $qr['totalSizeMm'] + $qr['textGapMm'] + $qr['textHeightMm']);
        }
    }

    public function test_matrix_size_is_read_from_tcpdf_for_each_value(): void
    {
        $calculator = new QrCodeLayout;
        $short = $calculator->matrix('6DROGUER-050');
        $long = $calculator->matrix(str_repeat('DENSE-2026-', 12));
        $this->assertSame(21, $short['modules']);
        $this->assertSame(41, $long['modules']);
    }

    public function test_dense_value_is_rejected_on_small_label_but_can_fit_larger_one(): void
    {
        $catalog = new A4LabelPresetCatalog;
        $calculator = new QrCodeLayout;
        $value = str_repeat('DENSE-2026-', 12);
        try {
            $calculator->calculate($value, $catalog->layout('38x21_2'), 0);
            $this->fail('The dense value should be rejected on the smallest preset.');
        } catch (QrCodeLayoutException $exception) {
            $this->assertStringContainsString('trop dense', $exception->getMessage());
        }

        $this->assertGreaterThanOrEqual(0.5, $calculator->calculate($value, $catalog->layout('70x37'), 0)['moduleMm']);
    }

    public function test_module_policy_marks_compact_values_without_silent_shrinking(): void
    {
        $catalog = new A4LabelPresetCatalog;
        $qr = (new QrCodeLayout)->calculate('6DROGUER-050', $catalog->layout('38x21_2'), 0);
        $this->assertGreaterThanOrEqual(0.4, $qr['moduleMm']);
        $this->assertSame($qr['moduleMm'] < 0.5, $qr['compact']);
    }

    public function test_tcpdf_padding_formula_matches_calculated_data_modules_and_quiet_zone(): void
    {
        $catalog = new A4LabelPresetCatalog;
        $calculator = new QrCodeLayout;
        foreach (['38x21_2', '70x37'] as $id) {
            $qr = $calculator->calculate('6NG15', $catalog->layout($id), 0);
            $dataRegionMm = ($qr['totalSizeMm'] * $qr['matrixModules']) / $qr['totalModules'];
            $dataModuleMm = $dataRegionMm / $qr['matrixModules'];
            $quietZoneMm = ($qr['totalSizeMm'] - $dataRegionMm) / 2;

            $this->assertEqualsWithDelta($qr['moduleMm'], $dataModuleMm, 0.000001);
            $this->assertEqualsWithDelta(QrCodeLayout::QUIET_ZONE_MODULES * $dataModuleMm, $quietZoneMm, 0.000001);
            $this->assertEqualsWithDelta($dataModuleMm, $qr['totalSizeMm'] / $qr['totalModules'], 0.000001);
        }
    }

    public function test_70x37_representative_qr_is_reduced_to_about_24mm_without_compromising_scan_quality(): void
    {
        $qr = (new QrCodeLayout)->calculate(
            '6ROULEMENT-278',
            (new A4LabelPresetCatalog)->layout('70x37'),
            0,
        );

        $this->assertLessThanOrEqual(24.0, $qr['totalSizeMm']);
        $this->assertGreaterThanOrEqual(QrCodeLayout::RECOMMENDED_MODULE_MM, $qr['moduleMm']);
        $this->assertFalse($qr['compact']);
        $this->assertSame($qr['matrixModules'] + (2 * QrCodeLayout::QUIET_ZONE_MODULES), $qr['totalModules']);
    }

    public function test_longer_representative_value_uses_the_same_actual_matrix_policy(): void
    {
        $qr = (new QrCodeLayout)->calculate('6SHN142638252891', (new A4LabelPresetCatalog)->layout('70x37'), 0);

        $this->assertSame(21, $qr['matrixModules']);
        $this->assertSame($qr['matrixModules'] + (2 * QrCodeLayout::QUIET_ZONE_MODULES), $qr['totalModules']);
        $this->assertGreaterThanOrEqual(QrCodeLayout::MIN_MODULE_MM, $qr['moduleMm']);
    }

    public function test_70x37_emplacement_text_fits_every_slot_without_crossing_label_boundaries(): void
    {
        $layout = (new A4LabelPresetCatalog)->layout('70x37');
        $calculator = new QrCodeLayout;
        $guides = $layout['guides'];

        foreach ($layout['elements'] as $slotIndex => $element) {
            $qr = $calculator->calculate(
                '6ROULEMENT-086&A001',
                $layout,
                $slotIndex,
                true,
            );
            $labelBottom = $element['yMm'] - 1.0 + $guides['labelHeightMm'];
            $textBottom = $qr['yMm']
                + $qr['totalSizeMm']
                + $qr['textGapMm']
                + $qr['textHeightMm'];

            $this->assertLessThanOrEqual(
                $labelBottom,
                $textBottom,
                'QR text exceeds the physical label in slot '.$slotIndex.'.'
            );
        }
    }

    public function test_long_70x37_payload_keeps_its_qr_and_text_block_inside_every_label(): void
    {
        $layout = (new A4LabelPresetCatalog)->layout('70x37');
        $calculator = new QrCodeLayout;
        $guides = $layout['guides'];
        $payload = 'LONG-REPRESENTATIVE-ARTICLE-000123&A001';

        foreach ($layout['elements'] as $slotIndex => $element) {
            $qr = $calculator->calculate($payload, $layout, $slotIndex, true);
            $labelRight = $element['xMm'] - 6.75 + $guides['labelWidthMm'];
            $labelBottom = $element['yMm'] - 1.0 + $guides['labelHeightMm'];

            $this->assertGreaterThanOrEqual($element['xMm'] - 6.75, $qr['xMm']);
            $this->assertLessThanOrEqual($labelRight, $qr['xMm'] + $qr['totalSizeMm']);
            $this->assertLessThanOrEqual(
                $labelBottom,
                $qr['yMm'] + $qr['totalSizeMm'] + $qr['textGapMm'] + $qr['textHeightMm'],
            );
        }
    }

    public function test_52x297_qr_layout_is_horizontal_and_fits_all_40_slots(): void
    {
        $layout = (new A4LabelPresetCatalog)->layout('52_5x29_7');
        $calculator = new QrCodeLayout;
        $pdf = new TCPDF('P', 'mm', [210, 297], true, 'UTF-8', false);
        $guides = $layout['guides'];
        $payload = 'CODE-ARTICLE-000123&A001';

        $origins = [];

        foreach ($layout['elements'] as $slotIndex => $element) {
            $qr = $calculator->calculate($payload, $layout, $slotIndex, true);
            $labelLeft = $guides['marginLeftMm'] + ($slotIndex % $guides['columns']) * ($guides['labelWidthMm'] + $guides['gapXMm']);
            $labelTop = $guides['marginTopMm'] + intdiv($slotIndex, $guides['columns']) * ($guides['labelHeightMm'] + $guides['gapYMm']);
            $labelRight = $labelLeft + $guides['labelWidthMm'];
            $labelBottom = $labelTop + $guides['labelHeightMm'];
            $emplacementBox = $calculator->emplacementBox($pdf, 'A001', $qr);
            $origins[] = $qr['slotOriginXMm'].','.$qr['slotOriginYMm'];

            $this->assertTrue($qr['horizontal']);
            $this->assertEqualsWithDelta($labelLeft, $qr['slotOriginXMm'], 0.001);
            $this->assertEqualsWithDelta($labelTop, $qr['slotOriginYMm'], 0.001);
            $this->assertGreaterThanOrEqual($labelLeft + 2.0, $qr['xMm']);
            $this->assertLessThanOrEqual($labelRight - 2.0, $qr['xMm'] + $qr['totalSizeMm']);
            $this->assertGreaterThanOrEqual($labelTop + 2.0, $qr['yMm']);
            $this->assertLessThanOrEqual($labelBottom - 2.0, $qr['yMm'] + $qr['totalSizeMm']);
            $this->assertGreaterThanOrEqual($labelLeft + 2.0, $qr['textXMm']);
            $this->assertLessThanOrEqual($labelRight - 2.0, $qr['textXMm'] + $qr['textWidthMm']);
            $this->assertGreaterThanOrEqual($labelTop + 2.0, $qr['textYMm']);
            $this->assertLessThanOrEqual($labelBottom - 2.0, $qr['textYMm'] + $qr['textHeightMm']);
            $this->assertGreaterThanOrEqual($labelLeft + 2.0, $emplacementBox['xMm']);
            $this->assertLessThanOrEqual($labelRight - 2.0, $emplacementBox['xMm'] + $emplacementBox['widthMm']);
            $this->assertGreaterThanOrEqual($labelTop + 2.0, $emplacementBox['yMm']);
            $this->assertLessThanOrEqual($labelBottom - 2.0, $emplacementBox['yMm'] + $emplacementBox['heightMm']);
            $this->assertGreaterThanOrEqual(QrCodeLayout::MIN_MODULE_MM, $qr['moduleMm']);
            $this->assertGreaterThanOrEqual(QrCodeLayout::RECOMMENDED_MODULE_MM, $qr['moduleMm']);
        }

        $this->assertCount(40, array_unique($origins));
        $this->assertSame([0.0, 0.0], $this->slotOrigin($calculator, $layout, 0));
        $this->assertSame([52.5, 0.0], $this->slotOrigin($calculator, $layout, 1));
        $this->assertSame([157.5, 0.0], $this->slotOrigin($calculator, $layout, 3));
        $this->assertSame([0.0, 29.7], $this->slotOrigin($calculator, $layout, 4));
        $this->assertSame([157.5, 267.3], $this->slotOrigin($calculator, $layout, 39));
    }

    public function test_52x297_qr_layout_keeps_code_payload_and_emplacement_optional(): void
    {
        $layout = (new A4LabelPresetCatalog)->layout('52_5x29_7');
        $calculator = new QrCodeLayout;
        $withEmplacement = $calculator->calculate('CODE-ARTICLE-000123&A001', $layout, 0, true);
        $withoutEmplacement = $calculator->calculate('CODE-ARTICLE-000123', $layout, 0, false);

        $this->assertSame(25, $withEmplacement['matrixModules']);
        $this->assertSame($withEmplacement['matrixModules'] + 8, $withEmplacement['totalModules']);
        $this->assertTrue($withEmplacement['emplacementBox']);
        $this->assertFalse($withoutEmplacement['emplacementBox']);
        $this->assertNull($withoutEmplacement['emplacementTextFontPt']);
    }

    public function test_52x297_emplacement_box_is_content_sized_centered_and_safe(): void
    {
        $layout = (new A4LabelPresetCatalog)->layout('52_5x29_7');
        $calculator = new QrCodeLayout;
        $pdf = new TCPDF('P', 'mm', [210, 297], true, 'UTF-8', false);
        $widths = [];

        foreach (['A1', 'A001', 'B021', 'LONG-LOCATION-01'] as $emplacement) {
            $qr = $calculator->calculate('6SHN1276879736898&'.$emplacement, $layout, 0, true);
            $box = $calculator->emplacementBox($pdf, $emplacement, $qr);
            $pdf->SetFont('helvetica', 'B', $box['fontPt']);
            $expectedWidth = min(
                $qr['textWidthMm'],
                $pdf->GetStringWidth($box['text']) + (2 * QrCodeLayout::HORIZONTAL_EMP_PADDING_X_MM),
            );

            $this->assertSame('Emp: '.$emplacement, $box['text']);
            $this->assertEqualsWithDelta($expectedWidth, $box['widthMm'], 0.001);
            $this->assertEqualsWithDelta(
                $qr['textXMm'] + (($qr['textWidthMm'] - $box['widthMm']) / 2),
                $box['xMm'],
                0.001,
            );
            $this->assertEqualsWithDelta(
                $qr['textYMm'] + $qr['codeTextHeightMm'] + QrCodeLayout::HORIZONTAL_EMP_VERTICAL_GAP_MM,
                $box['yMm'],
                0.001,
            );
            $this->assertGreaterThanOrEqual(2.0, $box['xMm']);
            $this->assertLessThanOrEqual(50.5, $box['xMm'] + $box['widthMm']);
            $this->assertLessThanOrEqual(27.7, $box['yMm'] + $box['heightMm']);
            $widths[$emplacement] = $box['widthMm'];
        }

        $this->assertLessThan($widths['A001'], $widths['A1']);
        $this->assertLessThan($widths['LONG-LOCATION-01'], $widths['B021']);
    }

    /** @return array{float, float} */
    private function slotOrigin(QrCodeLayout $calculator, array $layout, int $slotIndex): array
    {
        $qr = $calculator->calculate('6SHN1276879736898&A001', $layout, $slotIndex, true);

        return [$qr['slotOriginXMm'], $qr['slotOriginYMm']];
    }
}
