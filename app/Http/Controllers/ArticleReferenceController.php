<?php

namespace App\Http\Controllers;

use App\Models\ArticleReference;
use App\Services\ArticleReferences\ArticleReferenceExcelParser;
use App\Services\ArticleReferences\ArticleReferenceImportResult;
use App\Services\ArticleReferences\ArticleReferenceParseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ArticleReferenceController extends Controller
{
    public function index()
    {
        return view('article-references.index', [
            'referenceCount' => ArticleReference::count(),
            'lastImportAt' => ArticleReference::query()
                ->latest('updated_at')
                ->first()
                ?->updated_at,
        ]);
    }

    public function import(
        Request $request,
        ArticleReferenceExcelParser $parser,
    ): RedirectResponse {
        $validated = $request->validate([
            'excel_file' => [
                'required',
                'file',
                'max:10240',
                'mimes:xlsx,xls',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/octet-stream,application/zip',
            ],
        ]);

        $path = $validated['excel_file']->getRealPath();

        if (! is_string($path) || $path === '') {
            return back()->withErrors([
                'excel_file' => 'Le fichier envoye ne peut pas etre traite.',
            ]);
        }

        try {
            $import = $parser->parse($path);
        } catch (ArticleReferenceParseException $exception) {
            return back()->withErrors([
                'excel_file' => $exception->getMessage(),
            ]);
        }

        $result = DB::transaction(function () use ($import): ArticleReferenceImportResult {
            $codes = array_map(
                fn ($row): string => $row->codeArticle,
                $import->rows
            );

            $existingCodes = [];

            foreach (array_chunk($codes, 500) as $codeChunk) {
                array_push(
                    $existingCodes,
                    ...ArticleReference::query()
                        ->whereIn('code_article', $codeChunk)
                        ->pluck('code_article')
                        ->all()
                );
            }

            $existingLookup = array_fill_keys($existingCodes, true);
            $now = now();
            $records = [];

            foreach ($import->rows as $row) {
                $records[] = [
                    'code_article' => $row->codeArticle,
                    'designation' => $row->designation,
                    'emplacement' => $row->emplacement,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($records, 100) as $chunk) {
                ArticleReference::upsert(
                    $chunk,
                    ['code_article'],
                    ['designation', 'emplacement', 'updated_at']
                );
            }

            $updated = count($existingLookup);
            $total = count($records);

            return new ArticleReferenceImportResult(
                total: $total,
                inserted: $total - $updated,
                updated: $updated,
                duplicateRows: $import->duplicateRows,
                skippedBlankRows: $import->skippedBlankRows,
            );
        });

        return redirect()
            ->route('article-references.index')
            ->with('result', $result);
    }
}
