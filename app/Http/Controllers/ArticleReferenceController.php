<?php

namespace App\Http\Controllers;

use App\Models\ArticleReference;
use App\Services\ArticleReferences\ArticleReferenceImporter;
use App\Services\ArticleReferences\ArticleReferenceExcelParser;
use App\Services\ArticleReferences\ArticleReferenceParseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        ArticleReferenceImporter $importer,
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

        $result = $importer->import($import);

        return redirect()
            ->route('article-references.index')
            ->with('result', $result);
    }
}
