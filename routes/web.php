<?php

use App\Http\Controllers\BarcodeLabelController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ManualCatalogueController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BarcodeLabelController::class, 'index'])->name('labels.index');
Route::post('/labels/headers', [BarcodeLabelController::class, 'headers'])->name('labels.headers');
Route::post('/labels', [BarcodeLabelController::class, 'generate'])->name('labels.generate');
Route::get('/labels/{token}.pdf', [BarcodeLabelController::class, 'pdf'])->name('labels.pdf');
Route::get('/inventaire', [InventoryController::class, 'index'])->name('inventories.index');
Route::get('/inventaire/{uuid}', [InventoryController::class, 'show'])->name('inventories.show');
Route::post('/inventaire/export', [InventoryController::class, 'export'])->name('inventories.export');

Route::get('/catalogue', [ManualCatalogueController::class, 'index'])->name('catalogue.index');
Route::post('/catalogue', [ManualCatalogueController::class, 'generate'])->name('catalogue.generate');
Route::get('/catalogue/{token}.pdf', [ManualCatalogueController::class, 'pdf'])->name('catalogue.pdf');
