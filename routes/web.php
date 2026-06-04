<?php

use App\Http\Controllers\BaseAsignadaController;
use App\Http\Controllers\ClientePotencialController;
use App\Http\Controllers\GestionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\VisitaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('base-asignada.index'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [BaseAsignadaController::class, 'dashboard'])->name('dashboard');
    Route::get('dashboard/reporte', [BaseAsignadaController::class, 'dashboardReporte'])->name('dashboard.reporte');
    Route::post('dashboard/meta-mensual', [BaseAsignadaController::class, 'guardarMetaMensual'])->name('dashboard.meta-mensual');
    Route::get('dashboard/detalle', [BaseAsignadaController::class, 'dashboardDetalle'])->name('dashboard.detalle');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('base-asignada/importar', [BaseAsignadaController::class, 'importar'])->name('base-asignada.importar');
    Route::get('base-asignada/plantilla-csv', [BaseAsignadaController::class, 'plantillaCsv'])->name('base-asignada.plantilla-csv');
    Route::get('base-asignada-cerradas', [BaseAsignadaController::class, 'cerradasComercial'])->name('base-asignada.cerradas');
    Route::get('base-asignada-pendientes-comercial', [BaseAsignadaController::class, 'pendientesComercial'])->name('base-asignada.pendientes-comercial');
    Route::post('base-asignada/{id}/cambiar-estado-supervisor', [BaseAsignadaController::class, 'cambiarEstadoSupervisor'])->name('base-asignada.cambiar-estado-supervisor');
    Route::post('base-asignada/{id}/datos-basicos', [BaseAsignadaController::class, 'actualizarDatosBasicos'])->name('base-asignada.datos-basicos');
    Route::post('base-asignada/{id}/retomar', [BaseAsignadaController::class, 'retomarRegistro'])->name('base-asignada.retomar');
    Route::post('base-asignada/{id}/reabrir-contactado', [BaseAsignadaController::class, 'reabrirAContactado'])->name('base-asignada.reabrir-contactado');
    Route::post('base-asignada/{id}/desembolso', [BaseAsignadaController::class, 'solicitarDesembolso'])->name('base-asignada.desembolso.solicitar');
    Route::get('supervisor/comerciales', [BaseAsignadaController::class, 'comercialesSupervisor'])->name('supervisor.comerciales');
    Route::get('supervisor/comerciales/{comercialId}/gestion', [BaseAsignadaController::class, 'gestionComercialSupervisor'])->name('supervisor.comerciales.gestion');
    Route::get('supervisor/comerciales/{comercialId}/gestion/{loteRef}', [BaseAsignadaController::class, 'gestionComercialLoteSupervisor'])->name('supervisor.comerciales.gestion.lote');
    Route::get('base-asignada-pendientes', [BaseAsignadaController::class, 'gestionesPendientes'])->name('base-asignada.pendientes');
    Route::post('base-asignada-pendientes/{id}/aprobar', [BaseAsignadaController::class, 'aprobarPendiente'])->name('base-asignada.pendientes.aprobar');
    Route::post('base-asignada-pendientes/{id}/devolver', [BaseAsignadaController::class, 'devolverPendiente'])->name('base-asignada.pendientes.devolver');
    Route::post('base-asignada-desembolso-pendientes/{id}/aprobar', [BaseAsignadaController::class, 'aprobarDesembolso'])->name('base-asignada.desembolso.aprobar');
    Route::post('base-asignada-desembolso-pendientes/{id}/devolver', [BaseAsignadaController::class, 'devolverDesembolso'])->name('base-asignada.desembolso.devolver');
    Route::post('clientes-potenciales/{id}/desembolso', [ClientePotencialController::class, 'solicitarDesembolso'])->name('clientes-potenciales.desembolso.solicitar');
    Route::post('clientes-potenciales/{id}/datos-basicos', [ClientePotencialController::class, 'actualizarDatosBasicos'])->name('clientes-potenciales.datos-basicos');
    Route::post('clientes-potenciales/{id}/retomar', [ClientePotencialController::class, 'retomarRegistro'])->name('clientes-potenciales.retomar');
    Route::post('clientes-potenciales/importar', [ClientePotencialController::class, 'importar'])->name('clientes-potenciales.importar');
    Route::get('clientes-potenciales/plantilla-csv', [ClientePotencialController::class, 'plantillaCsv'])->name('clientes-potenciales.plantilla-csv');
    Route::post('clientes-potenciales-pendientes/{id}/aprobar', [ClientePotencialController::class, 'aprobarPendiente'])->name('clientes-potenciales.pendientes.aprobar');
    Route::post('clientes-potenciales-pendientes/{id}/devolver', [ClientePotencialController::class, 'devolverPendiente'])->name('clientes-potenciales.pendientes.devolver');
    Route::post('clientes-potenciales-desembolso-pendientes/{id}/aprobar', [ClientePotencialController::class, 'aprobarDesembolso'])->name('clientes-potenciales.desembolso.aprobar');
    Route::post('clientes-potenciales-desembolso-pendientes/{id}/devolver', [ClientePotencialController::class, 'devolverDesembolso'])->name('clientes-potenciales.desembolso.devolver');
    Route::get('base-asignada-lotes', [BaseAsignadaController::class, 'lotes'])->name('base-asignada.lotes');
    Route::get('base-asignada-lotes/{loteRef}', [BaseAsignadaController::class, 'verLote'])->name('base-asignada.lote');
    Route::post('base-asignada-lotes/{loteRef}/asignar', [BaseAsignadaController::class, 'asignarLote'])->name('base-asignada.lote.asignar');
    Route::get('historico-cedula', [BaseAsignadaController::class, 'historicoCedula'])->name('base-asignada.historico-cedula');
    Route::get('notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notificaciones/{id}/leer', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('visitas', [VisitaController::class, 'index'])->name('visitas.index');
    Route::post('visitas', [VisitaController::class, 'store'])->name('visitas.store');
    Route::get('visitas/{id}', [VisitaController::class, 'show'])->name('visitas.show');
    Route::post('visitas/{id}/registrar', [VisitaController::class, 'registrar'])->name('visitas.registrar');
    Route::resource('base-asignada', BaseAsignadaController::class);
    Route::resource('clientes-potenciales', ClientePotencialController::class);
    Route::post('gestiones', [GestionController::class, 'store'])->name('gestiones.store');
});
