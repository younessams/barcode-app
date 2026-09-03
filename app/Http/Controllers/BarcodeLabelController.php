<?php

namespace App\Http\Controllers;

use App\Services\BarcodeLabels\A4LabelPresetCatalog;
use App\Services\BarcodeLabels\BarcodeLabelPdf;
use App\Services\BarcodeLabels\CodeType;
use App\Services\BarcodeLabels\ExcelLabelParseException;
use App\Services\BarcodeLabels\ExcelLabelParser;
use App\Services\BarcodeLabels\LabelPresetException;
use App\Services\BarcodeLabels\QrCodeLayoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class BarcodeLabelController extends Controller
{
    public function index()
    {
        return view('barcode-labels', [
            'presets' => (new A4LabelPresetCatalog)->all(),
        ]);
    }

    public function headers(Request $request, ExcelLabelParser $parser)
    {
        $validated = $request->validate([
            'excel_file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/octet-stream,application/zip'],
        ]);
        $path = $validated['excel_file']->getRealPath();

        if (! is_string($path) || $path === '') {
            return response()->json(['message' => 'Le fichier envoye ne peut pas etre traite.'], 422);
        }

        try {
            return response()->json(['headers' => $parser->headers($path)]);
        } catch (ExcelLabelParseException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function generate(Request $request, ExcelLabelParser $parser, BarcodeLabelPdf $pdf, A4LabelPresetCatalog $catalog): RedirectResponse
    {
        $this->cleanupOldGeneratedPdfs();

        $validated = $request->validate([
            'excel_file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/octet-stream,application/zip'],
            'excel_column' => ['required', 'string', 'max:120'],
            'preset_id' => ['required', 'string', 'in:'.implode(',', $catalog->ids())],
            'code_type' => ['nullable', 'string', 'in:'.implode(',', CodeType::values())],
        ]);

        $file = $validated['excel_file'];
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return back()->withErrors(['excel_file' => 'Le fichier envoye ne peut pas etre traite.']);
        }

        if ($request->has('layout_json')) {
            return back()->withErrors(['preset_id' => 'Les mises en page personnalisees ne sont plus disponibles.'])->withInput();
        }

        try {
            $layout = $catalog->layout($validated['preset_id']);
            $labels = $parser->parse($path, $validated['excel_column']);
        } catch (ExcelLabelParseException|LabelPresetException $exception) {
            return back()->withErrors(['excel_file' => $exception->getMessage()])->withInput();
        }

        try {
            $content = $pdf->render($labels, $layout, $validated['code_type'] ?? CodeType::CODE128);
        } catch (QrCodeLayoutException $exception) {
            return back()->withErrors(['excel_file' => $exception->getMessage()])->withInput();
        }
        $pages = $pdf->pageCount(count($labels), $layout);
        $token = Str::random(40);
        $directory = storage_path('app/generated-labels');
        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.$token.'.pdf', $content);

        return redirect()->route('labels.index')->with('result', [
            'token' => $token,
            'labels' => count($labels),
            'pages' => $pages,
        ]);
    }

    public function pdf(string $token, Request $request): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);

        $path = storage_path('app/generated-labels'.DIRECTORY_SEPARATOR.$token.'.pdf');
        abort_unless(File::exists($path), 404);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response(File::get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="code-128-labels.pdf"',
        ]);
    }

    private function cleanupOldGeneratedPdfs(): void
    {
        $directory = storage_path('app/generated-labels');
        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < now()->subHours(6)->getTimestamp()) {
                File::delete($file->getPathname());
            }
        }
    }
}
