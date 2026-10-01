<?php

namespace Tests\Feature;

use Tests\TestCase;

final class InventoryExportTest extends TestCase
{
    public function test_inventory_shell_and_missing_uuid_render_without_database_records(): void
    {
        $this->get(route('inventories.index'))
            ->assertOk()
            ->assertSee('inventory-create-form', false)
            ->assertDontSee('Fichier de reference');

        $this->get(route('inventories.show', 'local-only-session'))
            ->assertOk()
            ->assertSee('Inventaire introuvable.');
    }

    public function test_export_endpoint_accepts_the_client_payload_and_uses_zone_filename(): void
    {
        $response = $this->postJson(route('inventories.export'), [
            'zone' => 'Zone A',
            'items' => [['codeArticle' => '00123', 'emplacement' => null, 'quantity' => '1.250']],
        ]);

        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('inventaire-zone-a-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_export_endpoint_rejects_invalid_client_payload(): void
    {
        $this->postJson(route('inventories.export'), [
            'items' => [['codeArticle' => 'A', 'quantity' => '1.0000']],
        ])->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => $message !== '');
    }

    public function test_scanner_contract_remains_and_only_export_uses_client_post(): void
    {
        $source = file_get_contents(resource_path('js/inventory.js'));

        foreach (['READY', 'DETECTED', 'SAVING', 'SCAN_FREEZE_MS', 'SCAN_CONFIRMATIONS', 'SCAN_CONFIRM_WINDOW_MS', 'SCAN_MIN_OVERLAP', 'ZXING_SCAN_DELAY_MS', 'BarcodeDetector', 'BrowserMultiFormatReader', 'configureTorch', 'freezeCameraFrame', 'confirmScanCandidate', 'getScanRegionInVideoPixels', 'detectedQuantity.focus()'] as $contract) {
            $this->assertStringContainsString($contract, $source);
        }

        $this->assertStringNotContainsString('inventories.items', $source);
        $this->assertStringNotContainsString('data-item-url', file_get_contents(resource_path('views/inventories/show.blade.php')));
    }
}
