@extends('layout')

@section('content')
<h2>{{ $base->nombre }}</h2>
<table>
    <tbody>
        <tr>
            <th>Estado actual</th><td><strong>{{ $base->estado?->nombre ?? 'Sin estado' }}</strong></td>
            <th>Comercial asignado</th><td>{{ $base->asesor?->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Supervisor</th><td>{{ $base->supervisor?->name ?? 'N/A' }}</td>
            <th>Cedula</th><td>{{ $base->cedula ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Linea de credito</th><td>{{ $base->linea_credito ?? 'N/A' }}</td>
            <th>Efectivo</th><td>{{ is_null($base->efectivo) ? 'N/A' : ($base->efectivo ? 'SI' : 'NO') }}</td>
        </tr>
        <tr>
            <th>Monto solicitado</th><td>{{ is_null($base->monto_solicitado) ? 'N/A' : number_format((float)$base->monto_solicitado, 0, ',', '.') }}</td>
            <th>Monto aprobado</th><td>{{ is_null($base->monto_linea_credito) ? 'N/A' : number_format((float)$base->monto_linea_credito, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Empresa</th><td>{{ $base->empresa }}</td>
            <th>Telefono</th><td>{{ $base->telefono }}</td>
        </tr>
        <tr>
            <th>Origen</th><td>{{ $base->origen ? ucfirst($base->origen) : 'N/A' }}</td>
            <th></th><td></td>
        </tr>
        <tr>
            <th>Tiempo invertido total</th>
            <td>{{ number_format((int)$tiempoInvertidoRegistroMin, 0, ',', '.') }} min</td>
            <th></th><td></td>
        </tr>
        <tr>
            <th>Observaciones</th><td colspan="3">{{ $base->observaciones ?: 'N/A' }}</td>
        </tr>
    </tbody>
</table>
@if($base->estado?->slug === 'devuelta' && $base->motivo_devolucion)
    <p><strong>Motivo de devolucion (supervisor):</strong> {{ $base->motivo_devolucion }}</p>
@endif

@php($esCerrado = $base->estado?->slug === 'cerrado')
@php($esEfectiva = $base->estado?->slug === 'efectiva')
@php($esPendienteSupervisor = $base->estado?->slug === 'pendiente-aprobacion-supervisor')
@php($esSupervisor = auth()->user()?->role === 'supervisor')
@if($esCerrado || $esEfectiva)
    <p><strong>Registro finalizado:</strong> no se permite ninguna modificacion adicional.</p>
@endif
@if($esPendienteSupervisor)
    <p><strong>Registro en proceso:</strong> esta en Pendiente de aprobacion (supervisor). No se puede modificar hasta que sea aprobado o devuelto.</p>
@endif
<h3>Nueva gestion</h3>
@if(!$esCerrado && !$esEfectiva && !$esPendienteSupervisor)
<form method="post" action="{{ route('gestiones.store') }}">
    @csrf
    <input type="hidden" name="base_asignada_id" value="{{ $base->id }}">
    <div class="inline-filters">
        <div class="field">
            <label>Tipo de gestion</label>
            <select name="tipo" required>
                @php($canalActual = old('tipo', 'llamada'))
                <option value="visita" @selected($canalActual === 'visita')>Visita</option>
                <option value="oficina" @selected($canalActual === 'oficina')>Oficina</option>
                <option value="llamada" @selected($canalActual === 'llamada')>Llamada</option>
                <option value="redes sociales" @selected($canalActual === 'redes sociales')>Redes sociales</option>
            </select>
        </div>
        <div class="field">
            <label>Estado resultante</label>
            <select name="estado_id" id="estado_id">
                <option value="">No cambiar</option>
                @foreach($estados as $estado)
                    <option value="{{ $estado->id }}" data-slug="{{ $estado->slug }}">{{ $estado->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Linea de credito</label>
            <select name="linea_credito">
                <option value="">No cambiar</option>
                @foreach($lineasCredito as $linea)
                    <option value="{{ $linea }}" @selected(old('linea_credito', $base->linea_credito) === $linea)>{{ $linea }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Proxima gestion (fecha y hora)</label>
            <input type="datetime-local" name="proxima_gestion_at" step="60" value="{{ old('proxima_gestion_at') }}">
        </div>
        <div class="field">
            <label>Monto solicitado</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" id="monto_solicitado" name="monto_solicitado" value="{{ old('monto_solicitado', is_null($base->monto_solicitado) ? '' : (int)$base->monto_solicitado) }}" placeholder="Ej: 10000000">
        </div>
        <div class="field">
            <label>Tiempo invertido (min)</label>
            <input type="number" min="1" max="1440" step="1" id="minutos_invertidos" name="minutos_invertidos" value="{{ old('minutos_invertidos') }}" placeholder="Ej: 15">
        </div>
    </div>
    <label>Detalle de gestion</label>
    <textarea name="detalle" required>{{ old('detalle') }}</textarea>
    <div class="inline-filters">
        <div class="field">
            <label>Efectivo</label>
            <select name="efectivo" id="efectivo" disabled>
                <option value="">Seleccione</option>
                <option value="SI" @selected(old('efectivo') === 'SI')>SI</option>
                <option value="NO" @selected(old('efectivo') === 'NO')>NO</option>
            </select>
        </div>
        <div class="field">
            <label>Monto aprobado</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" id="monto_linea_credito" name="monto_linea_credito" value="{{ old('monto_linea_credito') }}" placeholder="Ej: 10000000" disabled>
        </div>
    </div>
    <button type="submit">Registrar gestion</button>
</form>
@endif

<h3>Historial</h3>
<table>
    <thead><tr><th>Fecha</th><th>Registrado por</th><th>Tipo</th><th>Estado</th><th>Proxima gestion</th><th>Tiempo invertido</th><th>Detalle</th></tr></thead>
    <tbody>
        @forelse($gestiones as $gestion)
            <tr>
                <td>{{ $gestion->created_at }}</td>
                <td>{{ $gestion->asesor?->name ?? 'N/A' }}</td>
                <td>{{ $gestion->tipo }}</td>
                <td>{{ $gestion->estado?->nombre }}</td>
                <td>{{ $gestion->proxima_gestion_at ? $gestion->proxima_gestion_at->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>{{ $gestion->minutos_invertidos ? ($gestion->minutos_invertidos . ' min') : 'N/A' }}</td>
                <td>{{ $gestion->detalle }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Sin gestiones.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $gestiones->links() }}

<h3>Historico por cedula</h3>
@if($base->cedula)
<table>
    <thead>
        <tr>
            <th>Fecha carga</th>
            <th>Ultima modificacion</th>
            <th>Lote</th>
            <th>Estado</th>
            <th>Comercial</th>
            <th>Monto</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($historicoCedula as $h)
            <tr>
                <td>{{ $h->created_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $h->ultima_gestion_at ? $h->ultima_gestion_at->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>{{ $h->lote_nombre ?? 'SIN LOTE' }}</td>
                <td>{{ $h->estado?->nombre ?? 'Sin estado' }}</td>
                <td>{{ $h->asesor?->name ?? 'Sin asignar' }}</td>
                <td>{{ is_null($h->monto_linea_credito) ? 'N/A' : '$' . number_format((float)$h->monto_linea_credito, 0, ',', '.') }}</td>
                <td><a href="{{ route('base-asignada.show', $h->id) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="7">No hay registros anteriores para esta cedula.</td></tr>
        @endforelse
    </tbody>
</table>
@else
<p>No se encontraron registros relacionados por cedula, telefono o nombre.</p>
@endif
<script>
    const estado = document.getElementById('estado_id');
    const efectivo = document.getElementById('efectivo');
    const montoSolicitado = document.getElementById('monto_solicitado');
    const monto = document.getElementById('monto_linea_credito');

    function syncCierreFields() {
        const slug = estado.options[estado.selectedIndex]?.dataset?.slug || '';
        const esCerrado = slug === 'cerrado';
        efectivo.disabled = !esCerrado;
        const esEfectivoSi = efectivo.value === 'SI';
        monto.disabled = !esCerrado || !esEfectivoSi;
        efectivo.required = esCerrado;
        monto.required = esCerrado && esEfectivoSi;
        if (!esCerrado || !esEfectivoSi) {
            monto.value = '';
        }
        if (!esCerrado) {
            efectivo.value = '';
        }
    }

    function sanitizeMonto() {
        if (!monto) return;
        monto.value = (monto.value || '').replace(/\D+/g, '');
    }
    function sanitizeMontoSolicitado() {
        if (!montoSolicitado) return;
        montoSolicitado.value = (montoSolicitado.value || '').replace(/\D+/g, '');
    }

    estado.addEventListener('change', syncCierreFields);
    efectivo?.addEventListener('change', syncCierreFields);
    monto?.addEventListener('input', sanitizeMonto);
    montoSolicitado?.addEventListener('input', sanitizeMontoSolicitado);
    montoSolicitado?.closest('form')?.addEventListener('submit', sanitizeMontoSolicitado);
    monto?.closest('form')?.addEventListener('submit', sanitizeMonto);
    syncCierreFields();
</script>
@endsection
