<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\Fixtures\CreatesExcelFixtures;
use Tests\TestCase;

final class BarcodeLabelUploadTest extends TestCase
{
    use CreatesExcelFixtures;

    public function test_upload_generates_pdf_response_with_counts(): void
    {
        $path = $this->createWorkbook([['SKU'], ...array_map(fn (int $i): array => ['SKU-'.$i], range(1, 24))]);
        $response = $this->post(route('labels.generate'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), 'excel_column' => 'SKU', 'preset_id' => '70x37']);
        $response->assertRedirect(route('labels.index'))->assertSessionHas('result.labels', 24)->assertSessionHas('result.pages', 1);
        $pdfResponse = $this->get(route('labels.pdf', ['token' => session('result.token')]));
        $pdfResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
    }

    public function test_headers_endpoint_returns_actual_headers_in_order(): void
    {
        $path = $this->createWorkbook([['SKU', 'Tracking', 'Empty'], ['0007', 'TR-1', null]]);
        $response = $this->post(route('labels.headers'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)]);
        $response->assertOk()->assertJson(['headers' => ['SKU', 'Tracking']]);
    }

    public function test_header_discovery_keeps_code_article_or_first_header_order(): void
    {
        $path = $this->createWorkbook([['SKU', 'Tracking'], ['0007', 'TR-1']]);
        $upload = fn () => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->post(route('labels.headers'), ['excel_file' => $upload()]);
        $response->assertOk()->assertJsonPath('headers.0', 'SKU')->assertJsonPath('headers.1', 'Tracking');

        $pathWithCodeArticle = $this->createWorkbook([['SKU', 'Code Article', 'Tracking'], ['0007', 'CA-1', 'TR-1']]);
        $response = $this->post(route('labels.headers'), ['excel_file' => new UploadedFile($pathWithCodeArticle, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)]);
        $response->assertOk()->assertJsonPath('headers.1', 'Code Article');
    }

    public function test_upload_requires_excel_column_name(): void
    {
        $path = $this->createWorkbook([['SKU'], ['CODE-1']]);
        $response = $this->post(route('labels.generate'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), 'preset_id' => '70x37']);
        $response->assertSessionHasErrors('excel_column');
    }

    public function test_custom_layout_payload_is_rejected(): void
    {
        $path = $this->createWorkbook([['SKU'], ['CODE-1']]);
        $response = $this->post(route('labels.generate'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), 'excel_column' => 'SKU', 'preset_id' => '70x37', 'layout_json' => '{}']);
        $response->assertSessionHasErrors('preset_id');
    }

    public function test_explicit_qr_code_type_generates_a_pdf(): void
    {
        $path = $this->createWorkbook([['Code Article'], ['000012345'], ['ABC-0003']]);
        $response = $this->post(route('labels.generate'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), 'excel_column' => 'Code Article', 'preset_id' => '70x37', 'code_type' => 'qr']);
        $response->assertRedirect(route('labels.index'))->assertSessionHas('result.labels', 2);
        $pdf = $this->get(route('labels.pdf', ['token' => session('result.token')]));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringNotContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_upload_auto_detects_separate_emplacement_column(): void
    {
        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['6VISTH-649', 'Vis acier', 'A0011'],
        ]);

        $response = $this->post(route('labels.generate'), [
            'excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'excel_column' => 'Code Article',
            'preset_id' => '52_5x29_7',
            'code_type' => 'qr',
        ]);

        $response->assertRedirect(route('labels.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('result.labels', 1);
    }

    public function test_omitted_code_type_defaults_to_qr(): void
    {
        $path = $this->createWorkbook([['Code Article'], ['CODE-0001']]);
        $response = $this->post(route('labels.generate'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), 'excel_column' => 'Code Article', 'preset_id' => '52_5x29_7']);
        $response->assertRedirect(route('labels.index'))->assertSessionHas('result.labels', 1);
        $pdf = $this->get(route('labels.pdf', ['token' => session('result.token')]));
        $pdf->assertOk();
        $this->assertStringNotContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_explicit_code128_code_type_remains_available(): void
    {
        $path = $this->createWorkbook([['Code Article'], ['CODE-0001']]);
        $response = $this->post(route('labels.generate'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), 'excel_column' => 'Code Article', 'preset_id' => '52_5x29_7', 'code_type' => 'code128']);
        $response->assertRedirect(route('labels.index'))->assertSessionHas('result.labels', 1);
        $pdf = $this->get(route('labels.pdf', ['token' => session('result.token')]));
        $pdf->assertOk();
        $this->assertStringNotContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_invalid_code_type_is_rejected(): void
    {
        $path = $this->createWorkbook([['SKU'], ['CODE-1']]);
        $response = $this->post(route('labels.generate'), ['excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), 'excel_column' => 'SKU', 'preset_id' => '70x37', 'code_type' => 'datamatrix']);
        $response->assertSessionHasErrors('code_type');
    }
}
