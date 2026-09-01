<?php

use App\Http\Controllers\BarcodeLabelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BarcodeLabelController::class, 'index'])->name('labels.index');
Route::post('/labels', [BarcodeLabelController::class, 'generate'])->name('labels.generate');
Route::get('/labels/{token}.pdf', [BarcodeLabelController::class, 'pdf'])->name('labels.pdf');
