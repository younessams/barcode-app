<?php

use App\Http\Controllers\BarcodeLabelController;
use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BarcodeLabelController::class, 'index'])->name('labels.index');
Route::post('/labels/headers', [BarcodeLabelController::class, 'headers'])->name('labels.headers');
Route::post('/labels', [BarcodeLabelController::class, 'generate'])->name('labels.generate');
Route::get('/labels/{token}.pdf', [BarcodeLabelController::class, 'pdf'])->name('labels.pdf');
Route::get('/inventaire', [InventoryController::class, 'index'])->name('inventories.index');
Route::post('/inventaire', [InventoryController::class, 'store'])->name('inventories.store');
Route::get('/inventaire/{uuid}', [InventoryController::class, 'show'])->name('inventories.show');
Route::get('/inventaire/{uuid}/export', [InventoryController::class, 'export'])->name('inventories.export');
Route::post('/inventaire/{uuid}/complete', [InventoryController::class, 'complete'])->name('inventories.complete');
Route::post('/inventaire/{uuid}/reopen', [InventoryController::class, 'reopen'])->name('inventories.reopen');
Route::post('/inventaire/{uuid}/items', [InventoryController::class, 'storeItem'])->name('inventories.items.store');
Route::patch('/inventaire/{uuid}/items/{itemUuid}', [InventoryController::class, 'updateItem'])->name('inventories.items.update');
Route::delete('/inventaire/{uuid}/items/{itemUuid}', [InventoryController::class, 'destroyItem'])->name('inventories.items.destroy');
