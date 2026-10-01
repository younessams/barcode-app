<?php

namespace App\Http\Controllers;

use App\Services\ClientInventoryExcelExporter;
use App\Services\InventoryExportException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class InventoryController extends Controller
{
    public function index()
    {
        return view('inventories.index');
    }

    public function show(string $uuid)
    {
        return view('inventories.show', ['inventoryUuid' => $uuid]);
    }

    public function export(Request $request, ClientInventoryExcelExporter $exporter): BinaryFileResponse|JsonResponse
    {
        try {
            $payload = $request->json()->all();
            $path = $exporter->export($payload);
        } catch (InventoryExportException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'L export de l inventaire a échoué.'], 500);
        }

        $zone = isset($payload['zone']) && is_string($payload['zone'])
            ? Str::slug(trim($payload['zone']))
            : '';
        $filename = 'inventaire'.($zone !== '' ? '-'.$zone : '').'-'.now()->format('Y-m-d').'.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
