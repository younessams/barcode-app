<?php

namespace Tests\Feature;

use App\Models\ArticleReference;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Services\InventoryExcelExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Fixtures\CreatesExcelFixtures;
use Tests\TestCase;

final class InventoryTest extends TestCase
{
    use CreatesExcelFixtures;
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

    public function test_inventory_creation_still_accepts_name_only(): void
    {
        $this->post(route('inventories.store'), ['name' => 'Inventaire sans zone'])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_sessions', [
            'name' => 'Inventaire sans zone',
            'zone' => null,
        ]);
        $this->assertDatabaseCount('article_references', 0);
    }

    public function test_inventory_creation_rejects_reference_file_when_zone_is_blank(): void
    {
        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['ABC-001', 'Article 1', 'A11'],
        ]);

        $this->from(route('inventories.index'))
            ->post(route('inventories.store'), [
                'name' => 'Inventaire Zone A',
                'zone' => '  ',
                'article_reference_file' => $this->uploadReference($path),
            ])
            ->assertRedirect(route('inventories.index'))
            ->assertSessionHasErrors('zone');

        $this->assertDatabaseCount('inventory_sessions', 0);
        $this->assertDatabaseCount('article_references', 0);
    }

    public function test_inventory_creation_imports_zone_reference_file_and_creates_inventory(): void
    {
        ArticleReference::create([
            'code_article' => 'KEEP',
            'designation' => 'Keep',
            'emplacement' => 'K-01',
        ]);
        ArticleReference::create([
            'code_article' => 'ABC-001',
            'designation' => 'Old',
            'emplacement' => 'A10',
        ]);

        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['abc-001', 'Article 1', 'A11'],
            ['ABC-002', 'First', 'A12'],
            ['abc-002', 'Last', 'A13'],
            ['001ab-09', '', ''],
        ]);

        $response = $this->post(route('inventories.store'), [
            'name' => 'Inventaire Zone A',
            'zone' => ' Zone A ',
            'article_reference_file' => $this->uploadReference($path),
        ]);

        $session = InventorySession::first();
        $response->assertRedirect(route('inventories.show', $session->uuid));

        $this->assertDatabaseHas('inventory_sessions', [
            'name' => 'Inventaire Zone A',
            'zone' => 'Zone A',
        ]);
        $this->assertDatabaseHas('article_references', [
            'code_article' => 'ABC-001',
            'designation' => 'Article 1',
            'emplacement' => 'A11',
        ]);
        $this->assertDatabaseHas('article_references', [
            'code_article' => 'ABC-002',
            'designation' => 'Last',
            'emplacement' => 'A13',
        ]);
        $this->assertDatabaseHas('article_references', [
            'code_article' => '001AB-09',
            'designation' => null,
            'emplacement' => null,
        ]);
        $this->assertDatabaseHas('article_references', ['code_article' => 'KEEP']);
        $this->assertSame(4, ArticleReference::count());
    }

    public function test_inventory_creation_rejects_formula_reference_file_without_creating_inventory_or_partial_import(): void
    {
        ArticleReference::create([
            'code_article' => 'KEEP',
            'designation' => 'Keep',
            'emplacement' => 'K-01',
        ]);

        $this->from(route('inventories.index'))
            ->post(route('inventories.store'), [
                'name' => 'Inventaire Zone A',
                'zone' => 'Zone A',
                'article_reference_file' => $this->uploadReference($this->formulaReferenceWorkbook()),
            ])
            ->assertRedirect(route('inventories.index'))
            ->assertSessionHasErrors('article_reference_file');

        $this->assertDatabaseCount('inventory_sessions', 0);
        $this->assertSame(1, ArticleReference::count());
        $this->assertDatabaseHas('article_references', [
            'code_article' => 'KEEP',
            'designation' => 'Keep',
            'emplacement' => 'K-01',
        ]);
    }

    public function test_inventory_creation_page_exposes_optional_zone_reference_upload(): void
    {
        $response = $this->get(route('inventories.index'))
            ->assertOk()
            ->assertSee('id="zone"', false)
            ->assertSee('Fichier de reference de la zone (optionnel)')
            ->assertSee('Renseignez d abord une zone pour activer l import du fichier.')
            ->assertSee('Choisir un fichier Excel')
            ->assertSee('Fichier pret a etre importe avec cet inventaire.');

        $html = $response->getContent();

        $this->assertStringContainsString('name="article_reference_file"', $html);
        $this->assertStringContainsString('accept=".xlsx,.xls"', $html);
        $this->assertStringContainsString('disabled data-upload-input', $html);
        $this->assertStringContainsString("zone.addEventListener('input', syncUploadState)", $html);
    }

    public function test_item_creation_normalizes_code_article_and_generates_uuid(): void
    {
        $session = InventorySession::create(['name' => 'Test']);
        $response = $this->postJson(route('inventories.items.store', $session->uuid), ['code_article' => '  001ab-09  ', 'quantity' => 12]);
        $response->assertOk()->assertJsonPath('item.code_article', '001AB-09')->assertJsonPath('item.quantity', '12.000');
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
        $this->postJson($route, ['code_article' => 'abc-0003', 'quantity' => 12])->assertOk()
            ->assertJsonPath('item.code_article', 'ABC-0003');
        $this->postJson($route, ['code_article' => 'ABC-0003', 'quantity' => 5])->assertStatus(409)->assertJsonPath('duplicate', true);
        $this->postJson($route, ['code_article' => 'AbC-0003', 'quantity' => 5])->assertStatus(409)->assertJsonPath('duplicate', true);
        $this->assertDatabaseHas('inventory_items', ['code_article' => 'ABC-0003', 'quantity' => 12]);
        $this->postJson($route, ['code_article' => 'ABC-0003', 'quantity' => 5, 'mode' => 'add'])->assertOk()->assertJsonPath('item.quantity', '17.000');
        $this->postJson($route, ['code_article' => 'ABC-0003', 'quantity' => 5, 'mode' => 'replace'])->assertOk()->assertJsonPath('item.quantity', '5.000');
    }

    public function test_duplicate_add_uses_canonical_code_without_creating_a_second_row(): void
    {
        $session = InventorySession::create(['name' => 'Case add']);
        $route = route('inventories.items.store', $session->uuid);

        $this->postJson($route, ['code_article' => 'ABC-01', 'quantity' => '1.250'])->assertOk();
        $this->postJson($route, ['code_article' => 'abc-01', 'quantity' => '2.500', 'mode' => 'add'])
            ->assertOk()
            ->assertJsonPath('item.code_article', 'ABC-01')
            ->assertJsonPath('item.quantity', '3.750');

        $this->assertSame(1, $session->items()->count());
        $this->assertDatabaseHas('inventory_items', [
            'code_article' => 'ABC-01',
            'quantity' => '3.750',
        ]);
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
        ArticleReference::create(['code_article' => '6HYGSEC-009', 'designation' => 'Article connu', 'emplacement' => 'A-01']);
        $route = route('inventories.items.store', $session->uuid);
        $this->postJson($route, ['code_article' => '6hygsec-009', 'quantity' => 12])->assertJson(['items_count' => 1, 'total_quantity' => 12]);
        $this->postJson($route, ['code_article' => '000012345', 'quantity' => '1.250'])->assertJson(['items_count' => 2, 'total_quantity' => 13.25]);
        $path = app(InventoryExcelExporter::class)->export($session->fresh());
        $workbook = IOFactory::load($path);
        $sheet = $workbook->getActiveSheet();
        $this->assertSame(['Code Article', 'Designation', 'Emplacement', 'Quantité'], $sheet->rangeToArray('A1:D1')[0]);
        $this->assertCount(0, $sheet->getDrawingCollection());
        $this->assertSame('6HYGSEC-009', $sheet->getCell('A2')->getValue());
        $this->assertSame('Article connu', $sheet->getCell('B2')->getValue());
        $this->assertSame('A-01', $sheet->getCell('C2')->getValue());
        $this->assertSame(12.0, $sheet->getCell('D2')->getValue());
        $this->assertSame('000012345', $sheet->getCell('A3')->getValue());
        $this->assertNull($sheet->getCell('B3')->getValue());
        $this->assertNull($sheet->getCell('C3')->getValue());
        $this->assertSame(1.25, $sheet->getCell('D3')->getValue());
        unlink($path);
    }

    public function test_optional_qr_export_uses_column_e_and_keeps_code_article_payload(): void
    {
        $session = InventorySession::create(['name' => 'QR']);
        ArticleReference::create(['code_article' => '00123', 'designation' => 'Article QR', 'emplacement' => 'Q-01']);
        InventoryItem::create(['inventory_session_id' => $session->id, 'code_article' => '00123', 'quantity' => '1.500']);

        $path = app(InventoryExcelExporter::class)->export($session, true);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $drawing = $sheet->getDrawingCollection()[0];

        $this->assertSame(['Code Article', 'Designation', 'Emplacement', 'Quantité', 'QR Code'], $sheet->rangeToArray('A1:E1')[0]);
        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertSame('E2', $drawing->getCoordinates());
        $this->assertSame('00123', $drawing->getDescription());
        $this->assertSame(1.5, $sheet->getCell('D2')->getValue());
        unlink($path);
    }

    public function test_export_writes_reference_formula_like_text_as_literal_strings(): void
    {
        $session = InventorySession::create(['name' => 'Formula text']);
        ArticleReference::create([
            'code_article' => '00123',
            'designation' => '=HYPERLINK("https://example.com","click")',
            'emplacement' => '=1+1',
        ]);
        InventoryItem::create(['inventory_session_id' => $session->id, 'code_article' => '00123', 'quantity' => '2.500']);

        $path = app(InventoryExcelExporter::class)->export($session);
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('00123', $sheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame('=HYPERLINK("https://example.com","click")', $sheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
        $this->assertSame(2.5, $sheet->getCell('D2')->getValue());
        $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('B2')->getDataType());
        $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('C2')->getDataType());
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
        $this->assertCount(0, $sheet->getDrawingCollection());
        unlink($path);
    }

    public function test_inventory_items_do_not_require_article_references(): void
    {
        $session = InventorySession::create(['name' => 'Unknown reference']);

        $this->postJson(route('inventories.items.store', $session->uuid), [
            'code_article' => 'UNKNOWN-999',
            'quantity' => '0.125',
        ])->assertOk()
            ->assertJsonPath('item.code_article', 'UNKNOWN-999')
            ->assertJsonPath('item.quantity', '0.125');

        $this->assertDatabaseHas('inventory_items', [
            'code_article' => 'UNKNOWN-999',
            'quantity' => '0.125',
        ]);
        $this->assertDatabaseCount('article_references', 0);
    }

    public function test_code_article_normalization_migration_merges_inventory_collisions_exactly(): void
    {
        $first = InventorySession::create(['name' => 'First']);
        $second = InventorySession::create(['name' => 'Second']);
        $now = now();

        DB::table('inventory_items')->insert([
            ['uuid' => '11111111-1111-1111-1111-111111111111', 'inventory_session_id' => $first->id, 'code_article' => '6hygsec-009', 'quantity' => '2.500', 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => '22222222-2222-2222-2222-222222222222', 'inventory_session_id' => $first->id, 'code_article' => '6HYGSEC-009', 'quantity' => '3.250', 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => '33333333-3333-3333-3333-333333333333', 'inventory_session_id' => $second->id, 'code_article' => '6hygsec-009', 'quantity' => '1.125', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->runCodeArticleNormalizationMigration();

        $this->assertSame(2, InventoryItem::count());
        $this->assertDatabaseHas('inventory_items', [
            'id' => 1,
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'inventory_session_id' => $first->id,
            'code_article' => '6HYGSEC-009',
            'quantity' => '5.750',
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_session_id' => $second->id,
            'code_article' => '6HYGSEC-009',
            'quantity' => '1.125',
        ]);
    }

    public function test_code_article_normalization_migration_keeps_latest_article_reference_collision(): void
    {
        DB::table('article_references')->insert([
            ['code_article' => '6hygsec-009', 'designation' => 'Old', 'emplacement' => 'A-01', 'created_at' => '2026-09-14 08:00:00', 'updated_at' => '2026-09-14 08:00:00'],
            ['code_article' => '6HYGSEC-009', 'designation' => 'New', 'emplacement' => 'B-02', 'created_at' => '2026-09-14 08:30:00', 'updated_at' => '2026-09-14 09:00:00'],
        ]);

        $this->runCodeArticleNormalizationMigration();

        $this->assertSame(1, ArticleReference::count());
        $this->assertDatabaseHas('article_references', [
            'code_article' => '6HYGSEC-009',
            'designation' => 'New',
            'emplacement' => 'B-02',
        ]);
    }

    private function runCodeArticleNormalizationMigration(): void
    {
        $migration = include database_path('migrations/2026_09_14_121147_normalize_code_articles_to_uppercase.php');
        $migration->up();
    }

    private function uploadReference(string $path): UploadedFile
    {
        return new UploadedFile(
            $path,
            'article-references.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function formulaReferenceWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Code Article', 'Designation', 'Emplacement'],
            ['ABC-001', 'Article 1', 'A11'],
        ]);
        $sheet->setCellValue([2, 2], '=CONCAT("Article"," 1")');

        $path = tempnam(sys_get_temp_dir(), 'inventory-reference-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
