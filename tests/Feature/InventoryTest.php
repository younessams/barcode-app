<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Services\InventoryExcelExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_creation_generates_uuid_and_keeps_optional_zone(): void
    {
        $response = $this->post(route('inventories.store'), ['name' => 'Inventaire Septembre 2026', 'zone' => 'Zone A']);
        $session = InventorySession::first();
        $response->assertRedirect(route('inventories.show', $session->uuid));
        $this->assertNotNull($session->uuid);
        $this->assertSame('Zone A', $session->zone);
        $this->assertSame(InventorySession::STATUS_IN_PROGRESS, $session->status);
    }

    public function test_item_creation_normalizes_only_outer_whitespace_and_generates_uuid(): void
    {
        $session = InventorySession::create(['name' => 'Test']);
        $response = $this->postJson(route('inventories.items.store', $session->uuid), ['code_article' => '  000012345  ', 'quantity' => 12]);
        $response->assertOk()->assertJsonPath('item.code_article', '000012345')->assertJsonPath('item.quantity', 12);
        $item = $session->items()->first();
        $this->assertNotNull($item->uuid);
        $this->assertSame(1, $session->items()->count());
    }

    public function test_same_code_is_unique_per_inventory_but_allowed_in_another(): void
    {
        $first = InventorySession::create(['name' => 'One']);
        $second = InventorySession::create(['name' => 'Two']);
        $this->postJson(route('inventories.items.store', $first->uuid), ['code_article' => '6NG15', 'quantity' => 12])->assertOk();
        $this->postJson(route('inventories.items.store', $second->uuid), ['code_article' => '6NG15', 'quantity' => 8])->assertOk();
        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('inventory_items', ['inventory_session_id' => $first->id, 'code_article' => '6NG15']);
    }

    public function test_duplicate_scan_requires_explicit_add_or_replace(): void
    {
        $session = InventorySession::create(['name' => 'Test']);
        $route = route('inventories.items.store', $session->uuid);
        $this->postJson($route, ['code_article' => 'ABC-0003', 'quantity' => 12])->assertOk();
        $this->postJson($route, ['code_article' => 'ABC-0003', 'quantity' => 5])->assertStatus(409)->assertJsonPath('duplicate', true);
        $this->assertDatabaseHas('inventory_items', ['code_article' => 'ABC-0003', 'quantity' => 12]);
        $this->postJson($route, ['code_article' => 'ABC-0003', 'quantity' => 5, 'mode' => 'add'])->assertOk()->assertJsonPath('item.quantity', 17);
        $this->postJson($route, ['code_article' => 'ABC-0003', 'quantity' => 5, 'mode' => 'replace'])->assertOk()->assertJsonPath('item.quantity', 5);
    }

    public function test_zero_is_valid_and_negative_quantity_is_rejected(): void
    {
        $session = InventorySession::create(['name' => 'Test']);
        $route = route('inventories.items.store', $session->uuid);
        $this->postJson($route, ['code_article' => 'ZERO', 'quantity' => 0])->assertOk();
        $this->postJson($route, ['code_article' => 'NEG', 'quantity' => -1])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_completed_inventory_blocks_edits_until_reopened(): void
    {
        $session = InventorySession::create(['name' => 'Test']);
        $this->postJson(route('inventories.items.store', $session->uuid), ['code_article' => '6SHN142638252891', 'quantity' => 4])->assertOk();
        $this->post(route('inventories.complete', $session->uuid))->assertRedirect();
        $this->postJson(route('inventories.items.store', $session->uuid), ['code_article' => 'NEW', 'quantity' => 1])->assertStatus(422);
        $this->post(route('inventories.reopen', $session->uuid))->assertRedirect();
        $this->postJson(route('inventories.items.store', $session->uuid), ['code_article' => 'NEW', 'quantity' => 1])->assertOk();
    }

    public function test_inventory_screen_exposes_camera_workflow_and_shared_save_endpoint(): void
    {
        $session = InventorySession::create(['name' => 'Camera test']);

        $response = $this->get(route('inventories.show', $session->uuid))
            ->assertOk()
            ->assertSee('Demarrer la camera')
            ->assertSee('Saisir le code article manuellement')
            ->assertSee("Cloturer l'inventaire", false)
            ->assertSee(route('inventories.items.store', $session->uuid));

        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'data-detected-step'));
        $this->assertStringNotContainsString('data-quantity-step', $html);
        $this->assertStringNotContainsString('detectedQuantity.focus()', file_get_contents(resource_path('js/inventory.js')));

        $this->get(route('inventories.index'))
            ->assertOk()
            ->assertSee("Commencer l'inventaire", false)
            ->assertDontSee('Terminer');
    }

    public function test_totals_and_export_contain_only_the_three_business_columns(): void
    {
        $session = InventorySession::create(['name' => 'Test', 'zone' => 'Zone A']);
        $route = route('inventories.items.store', $session->uuid);
        $this->postJson($route, ['code_article' => '6NG15', 'quantity' => 12])->assertJson(['items_count' => 1, 'total_quantity' => 12]);
        $this->postJson($route, ['code_article' => '000012345', 'quantity' => 8])->assertJson(['items_count' => 2, 'total_quantity' => 20]);
        $path = app(InventoryExcelExporter::class)->export($session->fresh());
        $workbook = IOFactory::load($path);
        $sheet = $workbook->getActiveSheet();
        $this->assertSame(['Code Article', 'Quantité', 'QR Code'], $sheet->rangeToArray('A1:C1')[0]);
        $this->assertCount(2, $sheet->getDrawingCollection());
        $this->assertSame('000012345', $sheet->getCell('A3')->getValue());
        unlink($path);
    }

    public function test_nine_hundred_items_are_stored_and_exported_in_id_order(): void
    {
        $session = InventorySession::create(['name' => 'Large']);
        for ($index = 1; $index <= 900; $index++) {
            InventoryItem::create(['inventory_session_id' => $session->id, 'code_article' => sprintf('ITEM-%04d', $index), 'quantity' => $index % 10]);
        }
        $this->assertSame(900, $session->items()->count());
        $this->get(route('inventories.show', $session->uuid))->assertOk()->assertSee('ITEM-0900');
        $path = app(InventoryExcelExporter::class)->export($session);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('ITEM-0001', $sheet->getCell('A2')->getValue());
        $this->assertSame('ITEM-0900', $sheet->getCell('A901')->getValue());
        $this->assertCount(900, $sheet->getDrawingCollection());
        unlink($path);
    }
}
