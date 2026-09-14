<?php

namespace Tests\Feature;

use App\Models\ArticleReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\CreatesExcelFixtures;
use Tests\TestCase;

final class ArticleReferenceImportTest extends TestCase
{
    use CreatesExcelFixtures;
    use RefreshDatabase;

    public function test_import_creates_article_references(): void
    {
        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['00123', 'Article A', 'A-01'],
            ['00045', 'Article B', 'B-02'],
        ]);

        $this->post(route('article-references.import'), [
            'excel_file' => $this->upload($path),
        ])->assertRedirect(route('article-references.index'))
            ->assertSessionHas('result');

        $this->assertDatabaseHas('article_references', [
            'code_article' => '00123',
            'designation' => 'Article A',
            'emplacement' => 'A-01',
        ]);
        $this->assertSame(2, ArticleReference::count());
    }

    public function test_reimport_updates_and_inserts_without_deleting_omitted_references(): void
    {
        ArticleReference::create([
            'code_article' => 'KEEP',
            'designation' => 'Keep',
            'emplacement' => 'K-01',
        ]);
        ArticleReference::create([
            'code_article' => '00123',
            'designation' => 'Old',
            'emplacement' => 'A-01',
        ]);

        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['00123', 'New', 'B-04'],
            ['99999', 'Inserted', 'C-01'],
        ]);

        $this->post(route('article-references.import'), [
            'excel_file' => $this->upload($path),
        ])->assertRedirect(route('article-references.index'));

        $this->assertDatabaseHas('article_references', [
            'code_article' => '00123',
            'designation' => 'New',
            'emplacement' => 'B-04',
        ]);
        $this->assertDatabaseHas('article_references', ['code_article' => '99999']);
        $this->assertDatabaseHas('article_references', ['code_article' => 'KEEP']);
        $this->assertSame(3, ArticleReference::count());
    }

    public function test_duplicate_rows_use_last_row_wins_and_report_duplicates(): void
    {
        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['00123', 'First', 'A-01'],
            ['00123', 'Last', 'B-04'],
        ]);

        $response = $this->post(route('article-references.import'), [
            'excel_file' => $this->upload($path),
        ]);

        $result = $response->baseResponse->getSession()->get('result');

        $response->assertRedirect(route('article-references.index'));
        $this->assertSame(1, $result->duplicateRows);
        $this->assertDatabaseHas('article_references', [
            'code_article' => '00123',
            'designation' => 'Last',
            'emplacement' => 'B-04',
        ]);
        $this->assertSame(1, ArticleReference::count());
    }

    public function test_blank_designation_and_emplacement_are_accepted_as_null(): void
    {
        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['00123', '', ''],
        ]);

        $this->post(route('article-references.import'), [
            'excel_file' => $this->upload($path),
        ])->assertRedirect(route('article-references.index'));

        $this->assertDatabaseHas('article_references', [
            'code_article' => '00123',
            'designation' => null,
            'emplacement' => null,
        ]);
    }

    public function test_invalid_import_returns_validation_error_without_changing_existing_data(): void
    {
        ArticleReference::create([
            'code_article' => 'KEEP',
            'designation' => 'Keep',
            'emplacement' => 'K-01',
        ]);

        $path = $this->createWorkbook([
            ['Code Article', 'Designation', 'Emplacement'],
            ['', 'Missing code', 'A-01'],
        ]);

        $this->post(route('article-references.import'), [
            'excel_file' => $this->upload($path),
        ])->assertSessionHasErrors('excel_file');

        $this->assertSame(1, ArticleReference::count());
        $this->assertDatabaseHas('article_references', ['code_article' => 'KEEP']);
    }

    public function test_index_shows_reference_count(): void
    {
        ArticleReference::create(['code_article' => '00123']);

        $this->get(route('article-references.index'))
            ->assertOk()
            ->assertSee('Referentiel articles')
            ->assertSee('1');
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile(
            $path,
            'article-references.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
