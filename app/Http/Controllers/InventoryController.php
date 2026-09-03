<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Services\InventoryExcelExporter;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:120'], 'zone' => ['nullable', 'string', 'max:120']]);
        $session = InventorySession::create($validated);

        return redirect()->route('inventories.show', $session->uuid);
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
        $validated = $request->validate([
            'code_article' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'mode' => ['nullable', 'string', 'in:add,replace'],
        ]);
        $code = trim($validated['code_article']);

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
                        ? $item->quantity + (int) $validated['quantity']
                        : (int) $validated['quantity'];
                    $item->save();

                    return $item;
                }

                return $session->items()->create(['code_article' => $code, 'quantity' => $validated['quantity']]);
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
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:4294967295']]);
        if ($session->isCompleted()) {
            return $this->itemError($request, 'Cet inventaire est termine. Reouvrez-le pour le modifier.', 422);
        }

        $item = $session->items()->where('uuid', $itemUuid)->firstOrFail();
        $item->update(['quantity' => $validated['quantity']]);

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

    public function export(string $uuid, InventoryExcelExporter $exporter): BinaryFileResponse
    {
        $session = $this->find($uuid);
        $path = $exporter->export($session);
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

        return response()->json(['item' => $item, 'items_count' => $session->items_count, 'total_quantity' => (int) ($session->items_sum_quantity ?? 0)]);
    }

    private function itemError(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->withErrors(['code_article' => $message])->withInput();
        }

        return response()->json(['message' => $message], $status);
    }
}
