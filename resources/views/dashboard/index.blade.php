@extends('layout')

@section('content')
<h2>Dashboard</h2>
@if($esSupervisor)
<p>Resumen general de gestion comercial (vista supervisor).</p>
@else
<p>Resumen de mi gestion comercial (vista personal).</p>
@endif

<h3>Indicadores mensuales</h3>
<form method="get" action="{{ route('dashboard') }}" id="filtro-periodo" class="inline-filters">
    <div class="field">
        <label>Mes</label>
        <select name="mes" onchange="document.getElementById('filtro-periodo').submit()">
            @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
            @endfor
        </select>
    </div>
    <div class="field">
        <label>Ano</label>
        <input type="number" name="anio" value="{{ $anio }}" min="2000" max="2100" onchange="document.getElementById('filtro-periodo').submit()">
    </div>
    <a href="{{ route('dashboard') }}">Limpiar</a>
</form>
<p>Periodo seleccionado: <strong>{{ $inicioMes->locale('es')->translatedFormat('F Y') }}</strong></p>
<table>
    <thead>
        <tr>
            <th>Indicador</th>
            <th>Mes actual</th>
            <th>Mes anterior</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Registros cargados</td>
            <td>{{ number_format((int) $kpiMesActual['registros_cargados'], 0, ',', '.') }}</td>
            <td>{{ number_format((int) $kpiMesAnterior['registros_cargados'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Gestion mensual</td>
            <td>{{ number_format((float) $kpiMesActual['porcentaje_gestion'], 1) }}% ({{ number_format((int) $kpiMesActual['registros_gestionados'], 0, ',', '.') }} regs)</td>
            <td>{{ number_format((float) $kpiMesAnterior['porcentaje_gestion'], 1) }}% ({{ number_format((int) $kpiMesAnterior['registros_gestionados'], 0, ',', '.') }} regs)</td>
        </tr>
        <tr>
            <td>Cierre mensual</td>
            <td>{{ number_format((float) $kpiMesActual['porcentaje_cierre'], 1) }}% ({{ number_format((int) $kpiMesActual['cierres'], 0, ',', '.') }} cierres)</td>
            <td>{{ number_format((float) $kpiMesAnterior['porcentaje_cierre'], 1) }}% ({{ number_format((int) $kpiMesAnterior['cierres'], 0, ',', '.') }} cierres)</td>
        </tr>
        <tr>
            <td>Monto colocado</td>
            <td>${{ number_format((float) $kpiMesActual['monto'], 0, ',', '.') }}</td>
            <td>${{ number_format((float) $kpiMesAnterior['monto'], 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<h3>Indicadores del mes por canal</h3>
<table>
    <thead>
        <tr>
            <th>Canal</th>
            <th>Gestiones</th>
            <th>Registros unicos gestionados</th>
            <th>% sobre gestiones del mes</th>
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
            <tr><td colspan="4">Sin gestiones en el mes seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Cierres por canal (mes seleccionado)</h3>
<table>
    <thead>
        <tr>
            <th>Canal</th>
            <th>Solicitudes de cierre</th>
            <th>Registros unicos</th>
            <th>Cierres aprobados</th>
            <th>% aprobacion</th>
            <th>Monto aprobado</th>
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
                <td>{{ number_format((float) $porcAprob, 1) }}%</td>
                <td>${{ number_format((float) $canal->monto_aprobado, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin solicitudes de cierre por canal en el mes seleccionado.</td></tr>
        @endforelse
    </tbody>
</table>

@if(!$esSupervisor)
@php($tMesH = floor((int)$tiempoInvertidoMesMin / 60))
@php($tMesM = (int)$tiempoInvertidoMesMin % 60)
@php($tCierreH = floor((int)$promTiempoCierreMesMin / 60))
@php($tCierreM = (int)$promTiempoCierreMesMin % 60)
<h3>Indicadores de tiempo (mi gestion)</h3>
<table>
    <thead>
        <tr>
            <th>Indicador</th>
            <th>Valor</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Tiempo invertido mes</td>
            <td>{{ number_format((int)$tiempoInvertidoMesMin, 0, ',', '.') }} min ({{ $tMesH }}h {{ $tMesM }}m)</td>
        </tr>
        <tr>
            <td>Promedio tiempo hasta cierre (mes)</td>
            <td>{{ (int)$promTiempoCierreMesMin > 0 ? ($tCierreH . 'h ' . $tCierreM . 'm') : 'N/A' }}</td>
        </tr>
        <tr>
            <td>Cierres efectivos (SI)</td>
            <td>{{ number_format((int)$efectivoSiMes, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Cierres no efectivos (NO)</td>
            <td>{{ number_format((int)$efectivoNoMes, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
@endif

@if($esSupervisor)
<h3>Rendimiento por comercial (mes seleccionado)</h3>
<table>
    <thead>
        <tr>
            <th>Comercial</th>
            <th>Asignados mes</th>
            <th>Gestionados</th>
            <th>Pendientes aprobacion</th>
            <th>Cierres mes</th>
            <th>Cierre mensual</th>
            <th>% gestion</th>
            <th>Efectivo SI</th>
            <th>Efectivo NO</th>
            <th>Tiempo invertido mes</th>
            <th>Promedio tiempo a cierre</th>
            <th>Monto colocado mes</th>
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
                <td>{{ number_format((int)$comercial->tiempo_invertido_min_mes, 0, ',', '.') }} min ({{ $tiempoMesH }}h {{ $tiempoMesM }}m)</td>
                <td>{{ (int)$comercial->prom_tiempo_cierre_min > 0 ? ($promCierreH . 'h ' . $promCierreM . 'm') : 'N/A' }}</td>
                <td>${{ number_format((float) $comercial->monto_colocado_mes, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="12">Sin comerciales.</td></tr>
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
            <th>Pendientes aprobacion</th>
            <th>Devueltas</th>
            <th>Monto cerrado</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ number_format((int) $totalBases, 0, ',', '.') }}</td>
            <td>{{ number_format((int) $gestionadas, 0, ',', '.') }}</td>
            <td>{{ number_format((float) $porcentajeGestion, 1) }}%</td>
            <td>{{ number_format((int) $cerradas, 0, ',', '.') }}</td>
            <td>{{ number_format((float) $porcentajeCierre, 1) }}%</td>
            <td>{{ number_format((int) $pendientesAprobacion, 0, ',', '.') }}</td>
            <td>{{ number_format((int) $devueltas, 0, ',', '.') }}</td>
            <td>${{ number_format((float) $montoCerrado, 0, ',', '.') }}</td>
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
@endsection
