<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Services\ArticleReferences\ArticleReferenceExcelParser;
use App\Services\ArticleReferences\ArticleReferenceImporter;
use App\Services\ArticleReferences\ArticleReferenceParseException;
use App\Services\InventoryExcelExporter;
use App\Support\CodeArticleNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class InventoryController extends Controller
{
    public function index()
    {
        $inventories = InventorySession::query()
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->latest('started_at')
            ->get();

        return view('inventories.index', compact('inventories'));
    }

    public function store(
        Request $request,
        ArticleReferenceExcelParser $parser,
        ArticleReferenceImporter $importer,
    ): RedirectResponse
    {
        $request->merge([
            'zone' => $this->blankToNull($request->input('zone')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'zone' => ['nullable', 'required_with:article_reference_file', 'string', 'max:120'],
            'article_reference_file' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:xlsx,xls',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/octet-stream,application/zip',
            ],
        ], [
            'zone.required_with' => 'Renseignez une zone avant d importer un fichier de reference.',
        ]);

        $import = null;

        if ($request->hasFile('article_reference_file')) {
            $path = $validated['article_reference_file']->getRealPath();

            if (! is_string($path) || $path === '') {
                return back()->withErrors([
                    'article_reference_file' => 'Le fichier envoye ne peut pas etre traite.',
                ])->withInput();
            }

            try {
                $import = $parser->parse($path);
            } catch (ArticleReferenceParseException $exception) {
                return back()->withErrors([
                    'article_reference_file' => $exception->getMessage(),
                ])->withInput();
            }
        }

        $session = DB::transaction(function () use ($validated, $import, $importer): InventorySession {
            if ($import !== null) {
                $importer->import($import);
            }

            return InventorySession::create([
                'name' => $validated['name'],
                'zone' => $validated['zone'] ?? null,
            ]);
        });

        $redirect = redirect()->route('inventories.show', $session->uuid);

        if ($import !== null) {
            $redirect->with(
                'status',
                'Inventaire cree. Le fichier de reference de la zone a ete importe avec succes.'
            );
        }

        return $redirect;
    }

    public function show(string $uuid)
    {
        $inventory = $this->find($uuid);
        $inventory->load(['items' => fn ($query) => $query->orderBy('id')]);

        return view('inventories.show', compact('inventory'));
    }

    public function storeItem(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $session = $this->find($uuid);
        $this->normalizeQuantity($request);

        $validated = $request->validate([
            'code_article' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,3})?$/', 'min:0', 'max:4294967295'],
            'mode' => ['nullable', 'string', 'in:add,replace'],
        ]);
        $code = CodeArticleNormalizer::normalize($validated['code_article']);

        if ($code === '') {
            return $this->itemError($request, 'Le code article est obligatoire.', 422);
        }

        if ($session->isCompleted()) {
            return $this->itemError($request, 'Cet inventaire est termine. Reouvrez-le pour le modifier.', 422);
        }

        try {
            $item = DB::transaction(function () use ($session, $code, $validated) {
                $item = $session->items()->where('code_article', $code)->lockForUpdate()->first();
                if ($item !== null) {
                    if (($validated['mode'] ?? null) === null) {
                        return $item;
                    }

                    $item->quantity = $validated['mode'] === 'add'
                        ? $this->addQuantities($item->quantity, $validated['quantity'])
                        : $this->formatQuantity($validated['quantity']);
                    $item->save();

                    return $item;
                }

                return $session->items()->create([
                    'code_article' => $code,
                    'quantity' => $this->formatQuantity($validated['quantity']),
                ]);
            });
        } catch (QueryException) {
            return $this->itemError($request, 'Cet article vient deja d etre enregistre. Relisez sa quantite avant de continuer.', 409);
        }

        if ($item->wasRecentlyCreated === false && ! isset($validated['mode'])) {
            return response()->json(['duplicate' => true, 'item' => $item], 409);
        }

        return $this->itemSuccess($request, $session, $item);
    }

    public function updateItem(Request $request, string $uuid, string $itemUuid): JsonResponse|RedirectResponse
    {
        $session = $this->find($uuid);
        $this->normalizeQuantity($request);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,3})?$/', 'min:0', 'max:4294967295'],
        ]);
        if ($session->isCompleted()) {
            return $this->itemError($request, 'Cet inventaire est termine. Reouvrez-le pour le modifier.', 422);
        }

        $item = $session->items()->where('uuid', $itemUuid)->firstOrFail();
        $item->update([
            'quantity' => $this->formatQuantity($validated['quantity']),
        ]);

        return $this->itemSuccess($request, $session, $item);
    }

    public function destroyItem(Request $request, string $uuid, string $itemUuid): JsonResponse|RedirectResponse
    {
        $session = $this->find($uuid);
        if ($session->isCompleted()) {
            return $this->itemError($request, 'Cet inventaire est termine. Reouvrez-le pour le modifier.', 422);
        }

        $session->items()->where('uuid', $itemUuid)->firstOrFail()->delete();

        return $this->itemSuccess($request, $session, null);
    }

    public function complete(string $uuid): RedirectResponse
    {
        $session = $this->find($uuid);
        $session->update(['status' => InventorySession::STATUS_COMPLETED, 'finished_at' => now()]);

        return back();
    }

    public function reopen(string $uuid): RedirectResponse
    {
        $session = $this->find($uuid);
        $session->update(['status' => InventorySession::STATUS_IN_PROGRESS, 'finished_at' => null]);

        return back();
    }

    public function export(Request $request, string $uuid, InventoryExcelExporter $exporter): BinaryFileResponse
    {
        $session = $this->find($uuid);
        $includeQr = $request->boolean('include_qr');
        $path = $exporter->export($session, $includeQr);
        $zone = $session->zone ? '-'.Str::slug($session->zone) : '';
        $filename = 'inventaire'.$zone.'-'.now()->format('Y-m-d').'.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function find(string $uuid): InventorySession
    {
        return InventorySession::where('uuid', $uuid)->firstOrFail();
    }

    private function itemSuccess(Request $request, InventorySession $session, ?InventoryItem $item): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back();
        }

        $session->loadCount('items')->loadSum('items', 'quantity');

        return response()->json([
            'item' => $item,
            'items_count' => $session->items_count,
            'total_quantity' => (float) ($session->items_sum_quantity ?? 0),
        ]);
    }

    private function normalizeQuantity(Request $request): void
    {
        $request->merge([
            'quantity' => str_replace(',', '.', trim((string) $request->input('quantity'))),
        ]);
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function quantityToUnits(string|int|float $quantity): int
    {
        $quantity = str_replace(',', '.', trim((string) $quantity));
        [$whole, $decimal] = array_pad(explode('.', $quantity, 2), 2, '');
        $decimal = str_pad(substr($decimal, 0, 3), 3, '0');

        return ((int) $whole * 1000) + (int) $decimal;
    }

    private function unitsToQuantity(int $units): string
    {
        return sprintf('%d.%03d', intdiv($units, 1000), $units % 1000);
    }

    private function formatQuantity(string|int|float $quantity): string
    {
        return $this->unitsToQuantity($this->quantityToUnits($quantity));
    }

    private function addQuantities(string|int|float $current, string|int|float $added): string
    {
        $units = $this->quantityToUnits($current) + $this->quantityToUnits($added);

        if ($units > 4_294_967_295_000) {
            throw ValidationException::withMessages([
                'quantity' => 'La quantite totale depasse la limite autorisee.',
            ]);
        }

        return $this->unitsToQuantity($units);
    }

    private function itemError(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->withErrors(['code_article' => $message])->withInput();
        }

        return response()->json(['message' => $message], $status);
    }
}
