<?php

use App\Http\Controllers\BaseAsignadaController;
use App\Http\Controllers\ClientePotencialController;
use App\Http\Controllers\GestionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('base-asignada.index'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [BaseAsignadaController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('base-asignada/importar', [BaseAsignadaController::class, 'importar'])->name('base-asignada.importar');
    Route::get('base-asignada-cerradas', [BaseAsignadaController::class, 'cerradasComercial'])->name('base-asignada.cerradas');
    Route::get('base-asignada-pendientes-comercial', [BaseAsignadaController::class, 'pendientesComercial'])->name('base-asignada.pendientes-comercial');
    Route::post('base-asignada/{id}/cambiar-estado-supervisor', [BaseAsignadaController::class, 'cambiarEstadoSupervisor'])->name('base-asignada.cambiar-estado-supervisor');
    Route::post('base-asignada/{id}/reabrir-contactado', [BaseAsignadaController::class, 'reabrirAContactado'])->name('base-asignada.reabrir-contactado');
    Route::get('supervisor/comerciales', [BaseAsignadaController::class, 'comercialesSupervisor'])->name('supervisor.comerciales');
    Route::get('supervisor/comerciales/{comercialId}/gestion', [BaseAsignadaController::class, 'gestionComercialSupervisor'])->name('supervisor.comerciales.gestion');
    Route::get('supervisor/comerciales/{comercialId}/gestion/{loteRef}', [BaseAsignadaController::class, 'gestionComercialLoteSupervisor'])->name('supervisor.comerciales.gestion.lote');
    Route::get('base-asignada-pendientes', [BaseAsignadaController::class, 'gestionesPendientes'])->name('base-asignada.pendientes');
    Route::post('base-asignada-pendientes/{id}/aprobar', [BaseAsignadaController::class, 'aprobarPendiente'])->name('base-asignada.pendientes.aprobar');
    Route::post('base-asignada-pendientes/{id}/devolver', [BaseAsignadaController::class, 'devolverPendiente'])->name('base-asignada.pendientes.devolver');
    Route::get('base-asignada-lotes', [BaseAsignadaController::class, 'lotes'])->name('base-asignada.lotes');
    Route::get('base-asignada-lotes/{loteRef}', [BaseAsignadaController::class, 'verLote'])->name('base-asignada.lote');
    Route::post('base-asignada-lotes/{loteRef}/asignar', [BaseAsignadaController::class, 'asignarLote'])->name('base-asignada.lote.asignar');
    Route::get('historico-cedula', [BaseAsignadaController::class, 'historicoCedula'])->name('base-asignada.historico-cedula');
    Route::get('notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notificaciones/{id}/leer', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::resource('base-asignada', BaseAsignadaController::class);
    Route::resource('clientes-potenciales', ClientePotencialController::class);
    Route::post('gestiones', [GestionController::class, 'store'])->name('gestiones.store');
});
