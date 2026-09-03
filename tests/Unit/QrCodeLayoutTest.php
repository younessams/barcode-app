<?php

namespace Tests\Unit;

use App\Services\BarcodeLabels\A4LabelPresetCatalog;
use App\Services\BarcodeLabels\QrCodeLayout;
use App\Services\BarcodeLabels\QrCodeLayoutException;
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

    public function test_longer_representative_value_uses_the_same_actual_matrix_policy(): void
    {
        $qr = (new QrCodeLayout)->calculate('6SHN142638252891', (new A4LabelPresetCatalog)->layout('70x37'), 0);

        $this->assertSame(21, $qr['matrixModules']);
        $this->assertSame($qr['matrixModules'] + (2 * QrCodeLayout::QUIET_ZONE_MODULES), $qr['totalModules']);
        $this->assertGreaterThanOrEqual(QrCodeLayout::MIN_MODULE_MM, $qr['moduleMm']);
    }
}
