<?php

use App\Http\Controllers\BaseAsignadaController;
use App\Http\Controllers\ClientePotencialController;
use App\Http\Controllers\GestionController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('base-asignada.index'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('base-asignada/importar', [BaseAsignadaController::class, 'importar'])->name('base-asignada.importar');
    Route::get('base-asignada-pendientes', [BaseAsignadaController::class, 'gestionesPendientes'])->name('base-asignada.pendientes');
    Route::post('base-asignada-pendientes/{id}/aprobar', [BaseAsignadaController::class, 'aprobarPendiente'])->name('base-asignada.pendientes.aprobar');
    Route::post('base-asignada-pendientes/{id}/devolver', [BaseAsignadaController::class, 'devolverPendiente'])->name('base-asignada.pendientes.devolver');
    Route::get('base-asignada-lotes', [BaseAsignadaController::class, 'lotes'])->name('base-asignada.lotes');
    Route::get('base-asignada-lotes/{loteNombre}', [BaseAsignadaController::class, 'verLote'])->name('base-asignada.lote');
    Route::post('base-asignada-lotes/{loteNombre}/asignar', [BaseAsignadaController::class, 'asignarLote'])->name('base-asignada.lote.asignar');
    Route::resource('base-asignada', BaseAsignadaController::class);
    Route::resource('clientes-potenciales', ClientePotencialController::class);
    Route::post('gestiones', [GestionController::class, 'store'])->name('gestiones.store');
});
