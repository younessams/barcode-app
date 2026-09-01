<?php

namespace Tests\Feature;

use App\Services\BarcodeLabels\A4LabelLayout;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\CreatesExcelFixtures;
use Tests\TestCase;

final class BarcodeLabelUploadTest extends TestCase
{
    use CreatesExcelFixtures;

    public function test_upload_generates_pdf_response_with_counts(): void
    {
        $path = $this->createWorkbook([
            ['Code Article'],
            ...array_map(fn (int $i): array => ['CODE-'.$i], range(1, 24)),
        ]);

        $response = $this->post(route('labels.generate'), [
            'excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'excel_column' => 'Code Article',
            'layout_json' => json_encode((new A4LabelLayout)->default(), JSON_THROW_ON_ERROR),
        ]);

        $response->assertRedirect(route('labels.index'));
        $response->assertSessionHas('result.labels', 24);
        $response->assertSessionHas('result.pages', 1);

        $token = session('result.token');
        $pdfResponse = $this->get(route('labels.pdf', ['token' => $token]));

        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
    }

    public function test_upload_requires_excel_column_name(): void
    {
        $path = $this->createWorkbook([
            ['Code Article'],
            ['CODE-1'],
        ]);

        $response = $this->post(route('labels.generate'), [
            'excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'layout_json' => json_encode((new A4LabelLayout)->default(), JSON_THROW_ON_ERROR),
        ]);

        $response->assertSessionHasErrors('excel_column');
    }

    public function test_upload_rejects_invalid_layout_json(): void
    {
        $path = $this->createWorkbook([
            ['Code Article'],
            ['CODE-1'],
        ]);

        $response = $this->post(route('labels.generate'), [
            'excel_file' => new UploadedFile($path, 'labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'excel_column' => 'Code Article',
            'layout_json' => '{"mode":"custom","guides":{"columns":3,"rows":8},"elements":[]}',
        ]);

        $response->assertSessionHasErrors('layout_json');
    }
}
