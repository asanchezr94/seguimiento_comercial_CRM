@extends('layout')

@section('content')
<h2>Dashboard</h2>
@if($esSupervisor)
<p>Resumen general de gestion comercial (vista supervisor).</p>
@else
<p>Resumen de mi gestion comercial (vista personal).</p>
@endif

<div style="position:sticky; top:0; z-index:90; background:#fff; border:1px solid #d5e1eb; border-radius:12px; padding:10px 12px; margin:0 0 12px; box-shadow:0 6px 16px rgba(5,35,58,.08);">
    <form method="get" action="{{ route('dashboard') }}" id="filtro-periodo" class="inline-filters" style="margin:0;">
        <div class="field">
            <label>Tipo de periodo</label>
            <select name="periodo" id="periodo-dashboard" onchange="document.getElementById('filtro-periodo').submit()">
                <option value="mes" @selected($periodo === 'mes')>Mensual</option>
                <option value="anio" @selected($periodo === 'anio')>Anual</option>
            </select>
        </div>
        <div class="field">
            <label>Mes</label>
            <select name="mes" onchange="document.getElementById('filtro-periodo').submit()" @disabled($periodo === 'anio')>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                @endfor
            </select>
        </div>
        <div class="field">
            <label>Ano</label>
            <select name="anio" onchange="document.getElementById('filtro-periodo').submit()">
                @for($year = 2026; $year <= 2036; $year++)
                    <option value="{{ $year }}" @selected((int) $anio === $year)>{{ $year }}</option>
                @endfor
            </select>
        </div>
        <button type="button" id="btn-limpiar"><a href="{{ route('dashboard') }}">Limpiar</a></button>
        <button type="button" id="btn-descargar-dashboard">Descargar reporte Excel</button>
    </form>
    <p style="margin:8px 0 0;">Periodo seleccionado: <strong>{{ $periodoActualTitulo }}</strong></p>
</div>

@if($esSupervisor)
<h3>Meta por asesor ({{ $periodoTexto }})</h3>
<p><button type="button" id="btn-open-meta-modal">Asignar meta</button></p>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Asesor</th>
            <th>Monto desembolsado</th>
            <th>Monto meta</th>
            <th>Restante</th>
            <th>% cumplimiento</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($metasResumen as $meta)
            <tr>
                <td>{{ $meta->name }}</td>
                <td>${{ number_format((float) $meta->monto_colocado, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $meta->monto_meta, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $meta->restante, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $meta->cumplimiento, 1) }}%</td>
                <td>
                    <span style="font-weight:700; color:{{ $meta->cumple ? '#0f7a34' : '#b42318' }};">
                        {{ $meta->cumple ? 'Cumple' : 'No cumple' }}
                    </span>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No hay datos de metas. Si es la primera vez, crea la tabla de metas y define los montos.</td></tr>
        @endforelse
    </tbody>
</table>
@elseif($metaPersonal)
<h3>Mi meta ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Monto desembolsado</th>
            <th>Monto meta</th>
            <th>Restante</th>
            <th>% cumplimiento</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>${{ number_format((float) $metaPersonal->monto_colocado, 0, ',', '.') }}</td>
            <td>${{ number_format((float) $metaPersonal->monto_meta, 0, ',', '.') }}</td>
            <td>${{ number_format((float) $metaPersonal->restante, 0, ',', '.') }}</td>
            <td>{{ number_format((float) $metaPersonal->cumplimiento, 1) }}%</td>
            <td>
                <span style="font-weight:700; color:{{ $metaPersonal->cumple ? '#0f7a34' : '#b42318' }};">
                    {{ $metaPersonal->cumple ? 'Cumple' : 'No cumple' }}
                </span>
            </td>
        </tr>
    </tbody>
</table>
@endif

<h3>Indicadores {{ $periodo === 'anio' ? 'anuales' : 'mensuales' }}</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Indicador</th>
            <th>Periodo actual ({{ $periodoActualTitulo }})</th>
            <th>Periodo anterior ({{ $periodoAnteriorTitulo }})</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Registros cargados</td>
            <td><a href="{{ route('dashboard.detalle', ['tipo' => 'cargados', 'mes' => $mes, 'anio' => $anio, 'periodo' => $periodo]) }}">{{ number_format((int) $kpiMesActual['registros_cargados'], 0, ',', '.') }}</a></td>
            <td>{{ number_format((int) $kpiMesAnterior['registros_cargados'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Gestion {{ $periodo === 'anio' ? 'anual' : 'mensual' }}</td>
            <td>{{ number_format((float) $kpiMesActual['porcentaje_gestion'], 1) }}% (<a href="{{ route('dashboard.detalle', ['tipo' => 'gestionados', 'mes' => $mes, 'anio' => $anio, 'periodo' => $periodo]) }}">{{ number_format((int) $kpiMesActual['registros_gestionados'], 0, ',', '.') }}</a> regs)</td>
            <td>{{ number_format((float) $kpiMesAnterior['porcentaje_gestion'], 1) }}% ({{ number_format((int) $kpiMesAnterior['registros_gestionados'], 0, ',', '.') }} regs)</td>
        </tr>
        <tr>
            <td>Cierre {{ $periodo === 'anio' ? 'anual' : 'mensual' }}</td>
            <td>{{ number_format((float) $kpiMesActual['porcentaje_cierre'], 1) }}% (<a href="{{ route('dashboard.detalle', ['tipo' => 'cerrados', 'mes' => $mes, 'anio' => $anio, 'periodo' => $periodo]) }}">{{ number_format((int) $kpiMesActual['cierres'], 0, ',', '.') }}</a> cierres)</td>
            <td>{{ number_format((float) $kpiMesAnterior['porcentaje_cierre'], 1) }}% ({{ number_format((int) $kpiMesAnterior['cierres'], 0, ',', '.') }} cierres)</td>
        </tr>
        <tr>
            <td>Cierres efectivos</td>
            @php($cierresEfMesAct = max(0, (int)$kpiMesActual['cierres'] - (int)$kpiMesActual['cierres_no_efectivos']))
            @php($cierresEfMesAnt = max(0, (int)$kpiMesAnterior['cierres'] - (int)$kpiMesAnterior['cierres_no_efectivos']))
            @php($porcEfMesAct = (int)$kpiMesActual['cierres'] > 0 ? round(($cierresEfMesAct / (int)$kpiMesActual['cierres']) * 100, 1) : 0)
            @php($porcEfMesAnt = (int)$kpiMesAnterior['cierres'] > 0 ? round(($cierresEfMesAnt / (int)$kpiMesAnterior['cierres']) * 100, 1) : 0)
            <td>{{ number_format((float) $porcEfMesAct, 1) }}% ({{ number_format($cierresEfMesAct, 0, ',', '.') }})</td>
            <td>{{ number_format((float) $porcEfMesAnt, 1) }}% ({{ number_format($cierresEfMesAnt, 0, ',', '.') }})</td>
        </tr>
        <tr>
            <td>Cierres no efectivos</td>
            @php($porcNoEfMesAct = (int)$kpiMesActual['cierres'] > 0 ? round(((int)$kpiMesActual['cierres_no_efectivos'] / (int)$kpiMesActual['cierres']) * 100, 1) : 0)
            @php($porcNoEfMesAnt = (int)$kpiMesAnterior['cierres'] > 0 ? round(((int)$kpiMesAnterior['cierres_no_efectivos'] / (int)$kpiMesAnterior['cierres']) * 100, 1) : 0)
            <td>{{ number_format((float) $porcNoEfMesAct, 1) }}% ({{ number_format((int) $kpiMesActual['cierres_no_efectivos'], 0, ',', '.') }})</td>
            <td>{{ number_format((float) $porcNoEfMesAnt, 1) }}% ({{ number_format((int) $kpiMesAnterior['cierres_no_efectivos'], 0, ',', '.') }})</td>
        </tr>
        <tr>
            <td>Monto solicitado</td>
            <td>${{ number_format((float) ($kpiMesActual['monto_solicitado'] ?? 0), 0, ',', '.') }}</td>
            <td>${{ number_format((float) ($kpiMesAnterior['monto_solicitado'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Monto colocado</td>
            <td>${{ number_format((float) $kpiMesActual['monto'], 0, ',', '.') }}</td>
            <td>${{ number_format((float) $kpiMesAnterior['monto'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Monto desembolsado</td>
            <td>${{ number_format((float) ($kpiMesActual['monto_desembolsado'] ?? 0), 0, ',', '.') }}</td>
            <td>${{ number_format((float) ($kpiMesAnterior['monto_desembolsado'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Cantidad de desembolsos</td>
            <td>{{ number_format((int) ($kpiMesActual['desembolsos'] ?? 0), 0, ',', '.') }}</td>
            <td>{{ number_format((int) ($kpiMesAnterior['desembolsos'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Cantidad de vinculaciones</td>
            <td>{{ number_format((int) ($kpiMesActual['vinculaciones'] ?? 0), 0, ',', '.') }}</td>
            <td>{{ number_format((int) ($kpiMesAnterior['vinculaciones'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>% desembolsos vs cierres efectivos</td>
            @php($porcDesMesAct = $cierresEfMesAct > 0 ? round(((int)($kpiMesActual['desembolsos'] ?? 0) / $cierresEfMesAct) * 100, 1) : 0)
            @php($porcDesMesAnt = $cierresEfMesAnt > 0 ? round(((int)($kpiMesAnterior['desembolsos'] ?? 0) / $cierresEfMesAnt) * 100, 1) : 0)
            <td>{{ number_format((float) $porcDesMesAct, 1) }}%</td>
            <td>{{ number_format((float) $porcDesMesAnt, 1) }}%</td>
        </tr>
        <tr>
            <td>% diferencia (colocado vs solicitado)</td>
            @php($difMesAct = (float) ($kpiMesActual['monto_solicitado'] ?? 0) > 0 ? round((((float)$kpiMesActual['monto'] - (float)$kpiMesActual['monto_solicitado']) / (float)$kpiMesActual['monto_solicitado']) * 100, 1) : 0)
            @php($difMesAnt = (float) ($kpiMesAnterior['monto_solicitado'] ?? 0) > 0 ? round((((float)$kpiMesAnterior['monto'] - (float)$kpiMesAnterior['monto_solicitado']) / (float)$kpiMesAnterior['monto_solicitado']) * 100, 1) : 0)
            <td>{{ number_format((float) $difMesAct, 1) }}%</td>
            <td>{{ number_format((float) $difMesAnt, 1) }}%</td>
        </tr>
    </tbody>
</table>

<h3>{{ $esSupervisor ? 'Vinculaciones por usuario' : 'Mis vinculaciones' }} ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Usuario</th>
            <th>Vinculaciones</th>
            <th>Ahorros</th>
            <th>Creditos</th>
            @foreach($vinculacionLineasCredito as $linea)
                <th>{{ $linea }}</th>
            @endforeach
            <th>Creditos sin linea</th>
        </tr>
    </thead>
    <tbody>
        @forelse($vinculacionesPorUsuario as $vinculacion)
            <tr>
                <td>{{ $vinculacion->name }}</td>
                <td>{{ number_format((int) $vinculacion->total, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $vinculacion->ahorros, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $vinculacion->creditos, 0, ',', '.') }}</td>
                @foreach($vinculacionLineasCredito as $linea)
                    <td>{{ number_format((int) ($vinculacion->por_linea[$linea] ?? 0), 0, ',', '.') }}</td>
                @endforeach
                <td>{{ number_format((int) $vinculacion->creditos_sin_linea, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 5 + count($vinculacionLineasCredito) }}">Sin vinculaciones en el periodo seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Creditos por linea y estado de desembolso ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Linea de credito</th>
            <th>Total creditos</th>
            @foreach($desembolsoEstadosCredito as $estadoDesembolso)
                <th>{{ ucfirst($estadoDesembolso) }}</th>
            @endforeach
            <th>Sin estado desembolso</th>
        </tr>
    </thead>
    <tbody>
        @forelse($creditosLineaDesembolso as $creditoLinea)
            <tr>
                <td>{{ $creditoLinea->linea_credito }}</td>
                <td>{{ number_format((int) $creditoLinea->total, 0, ',', '.') }}</td>
                @foreach($desembolsoEstadosCredito as $estadoDesembolso)
                    <td>{{ number_format((int) ($creditoLinea->por_estado[$estadoDesembolso] ?? 0), 0, ',', '.') }}</td>
                @endforeach
                <td>{{ number_format((int) $creditoLinea->sin_estado, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 3 + count($desembolsoEstadosCredito) }}">Sin creditos por linea en el periodo seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Estados de desembolso vs cierres ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Estado desembolso</th>
            <th>Cerrados efectivos</th>
            <th>Cerrados no efectivos</th>
            <th>Total cerrados</th>
            <th>% efectivos</th>
            <th>% no efectivos</th>
        </tr>
    </thead>
    <tbody>
        @forelse($desembolsoResumen as $desembolso)
            @php($totalDesembolso = (int) $desembolso->total)
            <tr>
                <td>{{ ucfirst($desembolso->desembolso_estado) }}</td>
                <td>{{ number_format((int) $desembolso->efectivos, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $desembolso->no_efectivos, 0, ',', '.') }}</td>
                <td>{{ number_format($totalDesembolso, 0, ',', '.') }}</td>
                <td>{{ $totalDesembolso > 0 ? number_format(round(((int)$desembolso->efectivos / $totalDesembolso) * 100, 1), 1) : '0.0' }}%</td>
                <td>{{ $totalDesembolso > 0 ? number_format(round(((int)$desembolso->no_efectivos / $totalDesembolso) * 100, 1), 1) : '0.0' }}%</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin cierres con estado de desembolso en el periodo seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Cierres efectivos vs estados de desembolso ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Estado desembolso</th>
            <th>Cierres efectivos</th>
            <th>% sobre efectivos</th>
            <th>Monto solicitado</th>
            <th>Monto aprobado</th>
            <th>Monto desembolsado</th>
        </tr>
    </thead>
    <tbody>
        @php($totalCierresEfectivosDesembolso = (int) $desembolsoEfectivosResumen->sum('total'))
        @forelse($desembolsoEfectivosResumen as $desembolso)
            @php($porcEfectivosEstado = $totalCierresEfectivosDesembolso > 0 ? round(((int)$desembolso->total / $totalCierresEfectivosDesembolso) * 100, 1) : 0)
            <tr>
                <td>{{ ucfirst($desembolso->desembolso_estado) }}</td>
                <td>{{ number_format((int) $desembolso->total, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $porcEfectivosEstado, 1) }}%</td>
                <td>${{ number_format((float) $desembolso->monto_solicitado, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $desembolso->monto_aprobado, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $desembolso->monto_desembolsado, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin cierres efectivos con estado de desembolso en el periodo seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Tiempos operativos ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Indicador</th>
            <th>Valor</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Promedio tiempo a primera gestion</td>
            <td>{{ (int)$promPrimeraGestionMesMin > 0 ? number_format((int)$promPrimeraGestionMesMin, 0, ',', '.') . ' min' : 'N/A' }}</td>
        </tr>
        <tr>
            <td>Promedio tiempo aprobacion supervisor</td>
            <td>{{ (int)$promAprobacionSupervisorMesMin > 0 ? number_format((int)$promAprobacionSupervisorMesMin, 0, ',', '.') . ' min' : 'N/A' }}</td>
        </tr>
    </tbody>
</table>

<h3>{{ $esSupervisor ? 'Visitas por persona' : 'Mis visitas' }} ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Asesor</th>
            <th>Programadas</th>
            <th>Registradas</th>
            <th>Realizadas</th>
            <th>Canceladas</th>
            <th>Pendientes</th>
            <th>% realizadas</th>
        </tr>
    </thead>
    <tbody>
        @forelse($visitasPorPersona as $visitaResumen)
            <tr>
                <td>{{ $visitaResumen->name }}</td>
                <td>{{ number_format((int) $visitaResumen->total, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $visitaResumen->registradas, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $visitaResumen->realizadas, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $visitaResumen->canceladas, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $visitaResumen->pendientes, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $visitaResumen->porcentaje_realizadas, 1) }}%</td>
            </tr>
        @empty
            <tr><td colspan="7">Sin visitas en el periodo seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Indicadores por canal ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Canal</th>
            <th>Gestiones</th>
            <th>Registros unicos gestionados</th>
            <th>% sobre gestiones del periodo</th>
        </tr>
    </thead>
    <tbody>
        @php($totalCanalesMes = (int) $canalesMes->sum('total_gestiones'))
        @forelse($canalesMes as $canal)
            @php($porcCanal = $totalCanalesMes > 0 ? round(((int)$canal->total_gestiones / $totalCanalesMes) * 100, 1) : 0)
            <tr>
                <td>{{ ucfirst($canal->canal ?: 'sin canal') }}</td>
                <td>{{ number_format((int) $canal->total_gestiones, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $canal->registros_unicos, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $porcCanal, 1) }}%</td>
            </tr>
        @empty
            <tr><td colspan="4">Sin gestiones en el periodo seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Cierres por canal ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Canal</th>
            <th>Solicitudes de cierre</th>
            <th>Registros unicos</th>
            <th>Cierres aprobados</th>
            <th>No efectivos</th>
            <th>% aprobacion</th>
            <th>Monto solicitado</th>
            <th>Monto aprobado</th>
            <th>Monto desembolsado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($cierresCanalMes as $canal)
            @php($porcAprob = (int)$canal->solicitudes_cierre > 0 ? round(((int)$canal->cierres_aprobados / (int)$canal->solicitudes_cierre) * 100, 1) : 0)
            <tr>
                <td>{{ ucfirst($canal->canal ?: 'sin canal') }}</td>
                <td>{{ number_format((int) $canal->solicitudes_cierre, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $canal->registros_unicos, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $canal->cierres_aprobados, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $canal->cierres_no_efectivos, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $porcAprob, 1) }}%</td>
                <td>${{ number_format((float) $canal->monto_solicitado, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $canal->monto_aprobado, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $canal->monto_desembolsado, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="9">Sin solicitudes de cierre por canal en el periodo seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

@if(!$esSupervisor)
@php($tMesH = floor((int)$tiempoInvertidoMesMin / 60))
@php($tMesM = (int)$tiempoInvertidoMesMin % 60)
@php($tCierreH = floor((int)$promTiempoCierreMesMin / 60))
@php($tCierreM = (int)$promTiempoCierreMesMin % 60)
<h3>Indicadores de tiempo (mi gestion)</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Indicador</th>
            <th>Valor</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Tiempo invertido {{ $periodo === 'anio' ? 'año' : 'mes' }}</td>
            <td>{{ number_format((int)$tiempoInvertidoMesMin, 0, ',', '.') }} min ({{ $tMesH }}h {{ $tMesM }}m)</td>
        </tr>
        <tr>
            <td>Promedio tiempo hasta cierre ({{ $periodo === 'anio' ? 'año' : 'mes' }})</td>
            <td>{{ (int)$promTiempoCierreMesMin > 0 ? ($tCierreH . 'h ' . $tCierreM . 'm') : 'N/A' }}</td>
        </tr>
    </tbody>
</table>
@endif

@if($esSupervisor)
@push('page-modals')
<div id="meta-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Asignar meta mensual</h3>
            <button type="button" class="modal-close" id="btn-close-meta-modal">Cerrar</button>
        </div>
        <form method="post" action="{{ route('dashboard.meta-mensual') }}" class="inline-filters" style="margin-bottom:0;">
            @csrf
            <div class="field">
                <label>Asesor</label>
                <select name="user_id" required>
                    <option value="">Seleccione</option>
                    @foreach($comercialesResumen as $asesorMeta)
                        <option value="{{ $asesorMeta->id }}">{{ $asesorMeta->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Mes</label>
                <input type="number" name="mes" min="1" max="12" value="{{ $mes }}" required>
            </div>
            <div class="field">
                <label>Ano</label>
                <select name="anio" required>
                    @for($year = 2026; $year <= 2036; $year++)
                        <option value="{{ $year }}" @selected((int) $anio === $year)>{{ $year }}</option>
                    @endfor
                </select>
            </div>
            <div class="field">
                <label>Monto meta</label>
                <input type="text" name="monto_meta" inputmode="numeric" pattern="[0-9]*" placeholder="Ej: 50000000" required>
            </div>
            <button type="submit">Guardar meta</button>
        </form>
    </div>
</div>
@endpush

@push('page-scripts')
<script>
    (function () {
        const modal = document.getElementById('meta-modal');
        const openBtn = document.getElementById('btn-open-meta-modal');
        const closeBtn = document.getElementById('btn-close-meta-modal');
        if (!modal || !openBtn || !closeBtn) return;

        const open = () => {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        };
        const close = () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        };

        openBtn.addEventListener('click', open);
        closeBtn.addEventListener('click', close);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    })();
</script>
@endpush
@endif

<h3>Rendimiento por comercial ({{ $periodoTexto }})</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Comercial</th>
            <th>Asignados {{ $periodo === 'anio' ? 'año' : 'mes' }}</th>
            <th>Gestionados</th>
            <th>Pendientes aprobacion</th>
            <th>Cierres {{ $periodo === 'anio' ? 'año' : 'mes' }}</th>
            <th>Cierre {{ $periodo === 'anio' ? 'anual' : 'mensual' }}</th>
            <th>% gestion</th>
            <th>Efectivo SI</th>
            <th>Efectivo NO</th>
            <th>% efectivo SI</th>
            <th>% no efectivo</th>
            <th>Monto solicitado {{ $periodo === 'anio' ? 'año' : 'mes' }}</th>
            <th>Tiempo invertido {{ $periodo === 'anio' ? 'año' : 'mes' }}</th>
            <th>Promedio tiempo a cierre</th>
            <th>Monto colocado {{ $periodo === 'anio' ? 'año' : 'mes' }}</th>
            <th>Desembolsos {{ $periodo === 'anio' ? 'año' : 'mes' }}</th>
            <th>Monto desembolsado {{ $periodo === 'anio' ? 'año' : 'mes' }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($comercialesResumen as $comercial)
            @php($porcCom = (int)$comercial->total_registros > 0 ? round(((int)$comercial->gestionados_registros / (int)$comercial->total_registros) * 100, 1) : 0)
            @php($tiempoMesH = floor(((int)$comercial->tiempo_invertido_min_mes) / 60))
            @php($tiempoMesM = ((int)$comercial->tiempo_invertido_min_mes) % 60)
            @php($promCierreH = floor(((int)$comercial->prom_tiempo_cierre_min) / 60))
            @php($promCierreM = ((int)$comercial->prom_tiempo_cierre_min) % 60)
            <tr>
                <td>{{ $comercial->name }}</td>
                <td>{{ number_format((int) $comercial->asignados_mes, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $comercial->gestionados_registros, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $comercial->pendientes_registros, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $comercial->cierres_mes, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $comercial->porcentaje_cierre_vs_asignados, 1) }}%</td>
                <td>{{ number_format((float) $porcCom, 1) }}%</td>
                <td>{{ number_format((int)$comercial->efectivo_si_mes, 0, ',', '.') }}</td>
                <td>{{ number_format((int)$comercial->efectivo_no_mes, 0, ',', '.') }}</td>
                @php($porcEfSiCom = (int)$comercial->cierres_mes > 0 ? round(((int)$comercial->efectivo_si_mes / (int)$comercial->cierres_mes) * 100, 1) : 0)
                @php($porcEfNoCom = (int)$comercial->cierres_mes > 0 ? round(((int)$comercial->efectivo_no_mes / (int)$comercial->cierres_mes) * 100, 1) : 0)
                <td>{{ number_format((float)$porcEfSiCom, 1) }}%</td>
                <td>{{ number_format((float)$porcEfNoCom, 1) }}%</td>
                <td>${{ number_format((float)$comercial->monto_solicitado_mes, 0, ',', '.') }}</td>
                <td>{{ number_format((int)$comercial->tiempo_invertido_min_mes, 0, ',', '.') }} min ({{ $tiempoMesH }}h {{ $tiempoMesM }}m)</td>
                <td>{{ (int)$comercial->prom_tiempo_cierre_min > 0 ? ($promCierreH . 'h ' . $promCierreM . 'm') : 'N/A' }}</td>
                <td>${{ number_format((float) $comercial->monto_colocado_mes, 0, ',', '.') }}</td>
                <td>{{ number_format((int) ($comercial->desembolsos_mes ?? 0), 0, ',', '.') }}</td>
                <td>${{ number_format((float) $comercial->monto_desembolsado_mes, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="17">Sin comerciales.</td></tr>
        @endforelse
    </tbody>
</table>

@if($esSupervisor)
<h3>Acumulado general por usuario</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Usuario</th>
            <th>Total asignados</th>
            <th>Gestionados</th>
            <th>% gestion</th>
            <th>Cerrados</th>
            <th>% cierre</th>
            <th>Pendientes aprobacion</th>
            <th>Efectivo SI</th>
            <th>Efectivo NO</th>
            <th>% efectivo SI</th>
            <th>% no efectivo</th>
            <th>Monto solicitado</th>
            <th>Monto aprobado</th>
            <th>Monto desembolsado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($acumuladoPorUsuario as $usuario)
            <tr>
                <td>{{ $usuario->name }}</td>
                <td>{{ number_format((int) $usuario->ac_total, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $usuario->ac_gestionados, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $usuario->ac_porcentaje_gestion, 1) }}%</td>
                <td>{{ number_format((int) $usuario->ac_cerrados, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $usuario->ac_porcentaje_cierre, 1) }}%</td>
                <td>{{ number_format((int) $usuario->ac_pendientes, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $usuario->ac_efectivo_si, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $usuario->ac_efectivo_no, 0, ',', '.') }}</td>
                @php($porcEfSiAc = (int)$usuario->ac_cerrados > 0 ? round(((int)$usuario->ac_efectivo_si / (int)$usuario->ac_cerrados) * 100, 1) : 0)
                @php($porcEfNoAc = (int)$usuario->ac_cerrados > 0 ? round(((int)$usuario->ac_efectivo_no / (int)$usuario->ac_cerrados) * 100, 1) : 0)
                <td>{{ number_format((float) $porcEfSiAc, 1) }}%</td>
                <td>{{ number_format((float) $porcEfNoAc, 1) }}%</td>
                <td>${{ number_format((float) $usuario->ac_monto_solicitado, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $usuario->ac_monto_aprobado, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $usuario->ac_monto_desembolsado, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="14">Sin usuarios gestores.</td></tr>
        @endforelse
    </tbody>
</table>
@endif

<h3>{{ $esSupervisor ? 'Resumen historico (acumulado general)' : 'Resumen historico (acumulado personal)' }}</h3>
<table>
    <thead>
        <tr>
            <th>Total registros</th>
            <th>Gestionadas</th>
            <th>% gestion</th>
            <th>Cerradas</th>
            <th>% cierre</th>
            <th>% cierres efectivos</th>
            <th>% cierres no efectivos</th>
            <th>Cerrados no efectivos</th>
            <th>Pendientes aprobacion</th>
            <th>Devueltas</th>
            <th>Monto cerrado</th>
            <th>Monto desembolsado</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ number_format((int) $totalBases, 0, ',', '.') }}</td>
            <td>{{ number_format((int) $gestionadas, 0, ',', '.') }}</td>
            <td>{{ number_format((float) $porcentajeGestion, 1) }}%</td>
            <td>{{ number_format((int) $cerradas, 0, ',', '.') }}</td>
            <td>{{ number_format((float) $porcentajeCierre, 1) }}%</td>
            @php($porcEfHist = (int)$cerradas > 0 ? round((((int)$cerradas - (int)$cerradasNoEfectivas) / (int)$cerradas) * 100, 1) : 0)
            @php($porcNoEfHist = (int)$cerradas > 0 ? round(((int)$cerradasNoEfectivas / (int)$cerradas) * 100, 1) : 0)
            <td>{{ number_format((float) $porcEfHist, 1) }}%</td>
            <td>{{ number_format((float) $porcNoEfHist, 1) }}%</td>
            <td><a href="{{ route('dashboard.detalle', ['tipo' => 'no_efectivos', 'mes' => $mes, 'anio' => $anio, 'periodo' => $periodo]) }}">{{ number_format((int) $cerradasNoEfectivas, 0, ',', '.') }}</a></td>
            <td><a href="{{ route('dashboard.detalle', ['tipo' => 'pendientes', 'mes' => $mes, 'anio' => $anio, 'periodo' => $periodo]) }}">{{ number_format((int) $pendientesAprobacion, 0, ',', '.') }}</a></td>
            <td><a href="{{ route('dashboard.detalle', ['tipo' => 'devueltas', 'mes' => $mes, 'anio' => $anio, 'periodo' => $periodo]) }}">{{ number_format((int) $devueltas, 0, ',', '.') }}</a></td>
            <td>${{ number_format((float) $montoCerrado, 0, ',', '.') }}</td>
            <td>${{ number_format((float) $montoDesembolsado, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

@if($esSupervisor)
<h3>Embudo por estado</h3>
<table>
    <thead>
        <tr>
            <th>Estado</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($estadosResumen as $estado)
            <tr>
                <td>{{ $estado->estado_nombre }}</td>
                <td>{{ number_format((int) $estado->total, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="2">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>
@endif

@push('page-scripts')
<script>
    (function () {
        const btn = document.getElementById('btn-descargar-dashboard');
        if (!btn) return;

        const clean = (value) => (value || '').replace(/\s+/g, ' ').trim();
        const escapeHtml = (value) => clean(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        btn.addEventListener('click', function () {
            let html = `
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; color: #10263f; }
                        h1 { color: #073f61; font-size: 20px; }
                        h2 { color: #073f61; font-size: 16px; margin-top: 22px; }
                        table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
                        th { background: #0b6f9c; color: #ffffff; font-weight: bold; border: 1px solid #07577c; padding: 8px; }
                        td { border: 1px solid #9fc5dc; padding: 7px; }
                        tr:nth-child(even) td { background: #f3f8fc; }
                        .meta td { background: #eaf5fb; font-weight: bold; }
                        .section-title { background: #d8edf8; color: #073f61; font-weight: bold; border: 1px solid #9fc5dc; padding: 9px; }
                    </style>
                </head>
                <body>
                    <h1>Reporte dashboard - ${escapeHtml('{{ $periodoActualTitulo }}')}</h1>
                    <table class="meta">
                        <tr><td>Tipo periodo</td><td>${escapeHtml('{{ $periodo === 'anio' ? 'Anual' : 'Mensual' }}')}</td></tr>
                        <tr><td>Generado</td><td>${escapeHtml(new Date().toLocaleString('es-CO'))}</td></tr>
                    </table>
            `;

            document.querySelectorAll('main.panel table').forEach((table) => {
                const title = (() => {
                    let node = table.previousElementSibling;
                    while (node) {
                        if (['H2', 'H3'].includes(node.tagName)) {
                            return clean(node.textContent);
                        }
                        node = node.previousElementSibling;
                    }
                    return 'Tabla dashboard';
                })();

                html += `<h2>${escapeHtml(title)}</h2>`;
                html += '<table>';

                const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent);
                if (headers.length > 0) {
                    html += '<thead><tr>';
                    headers.forEach((header) => {
                        html += `<th>${escapeHtml(header)}</th>`;
                    });
                    html += '</tr></thead>';
                }

                html += '<tbody>';
                table.querySelectorAll('tbody tr').forEach((tr) => {
                    const cells = Array.from(tr.querySelectorAll('th, td')).map((td) => td.textContent);
                    if (cells.length > 0) {
                        html += '<tr>';
                        cells.forEach((cell) => {
                            html += `<td>${escapeHtml(cell)}</td>`;
                        });
                        html += '</tr>';
                    }
                });
                html += '</tbody></table>';
            });

            html += '</body></html>';

            const blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const periodo = '{{ $periodo === 'anio' ? $anio : sprintf('%04d_%02d', $anio, $mes) }}';
            a.href = url;
            a.download = `dashboard_tablas_${periodo}.xls`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        });
    })();
</script>
@endpush
@endsection

