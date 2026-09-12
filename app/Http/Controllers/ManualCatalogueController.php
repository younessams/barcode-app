<?php

namespace App\Http\Controllers;

use App\Services\ManualCatalogue\ManualCatalogueExcelParser;
use App\Services\ManualCatalogue\ManualCatalogueParseException;
use App\Services\ManualCatalogue\ManualCataloguePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ManualCatalogueController extends Controller
{
    public function index()
    {
        return view('manual-catalogue');
    }

    public function generate(
        Request $request,
        ManualCatalogueExcelParser $parser,
        ManualCataloguePdf $pdf,
    ): RedirectResponse {
        $this->cleanupOldGeneratedPdfs();

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
            $items = $parser->parse($path);
        } catch (ManualCatalogueParseException $exception) {
            return back()->withErrors([
                'excel_file' => $exception->getMessage(),
            ]);
        }

        $content = $pdf->render($items);

        $token = Str::random(40);
        $directory = storage_path('app/generated-manual-catalogue');

        File::ensureDirectoryExists($directory);

        File::put(
            $directory.DIRECTORY_SEPARATOR.$token.'.pdf',
            $content
        );

        return redirect()
            ->route('catalogue.index')
            ->with('result', [
                'token' => $token,
                'items' => count($items),
                'pages' => $pdf->pageCount(count($items)),
            ]);
    }

    public function pdf(string $token, Request $request): Response
    {
        abort_unless(
            preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1,
            404
        );

        $path = storage_path(
            'app/generated-manual-catalogue'
            .DIRECTORY_SEPARATOR
            .$token
            .'.pdf'
        );

        abort_unless(File::exists($path), 404);

        $disposition = $request->boolean('download')
            ? 'attachment'
            : 'inline';

        return response(
            File::get($path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    $disposition.'; filename="catalogue-qr.pdf"',
            ]
        );
    }

    private function cleanupOldGeneratedPdfs(): void
    {
        $directory = storage_path(
            'app/generated-manual-catalogue'
        );

        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            if (
                $file->getMTime()
                < now()->subHours(6)->getTimestamp()
            ) {
                File::delete($file->getPathname());
            }
        }
    }
}
