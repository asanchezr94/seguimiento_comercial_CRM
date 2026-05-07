@extends('layout')

@section('content')
<h2>{{ $base->nombre }}</h2>
<p>Estado actual: <strong>{{ $base->estado?->nombre ?? 'Sin estado' }}</strong></p>
<p>Supervisor: {{ $base->supervisor?->name ?? 'N/A' }}</p>
<p>Comercial asignado: {{ $base->asesor?->name ?? 'N/A' }}</p>
<p>Cedula: {{ $base->cedula ?? 'N/A' }}</p>
<p>Linea de credito: {{ $base->linea_credito ?? 'N/A' }}</p>
<p>Efectivo: {{ is_null($base->efectivo) ? 'N/A' : ($base->efectivo ? 'SI' : 'NO') }}</p>
<p>Monto linea de credito: {{ is_null($base->monto_linea_credito) ? 'N/A' : number_format((float)$base->monto_linea_credito, 0, ',', '.') }}</p>
<p>Empresa: {{ $base->empresa }}</p>
<p>Telefono: {{ $base->telefono }}</p>
<p>Email: {{ $base->email }}</p>
<p>Observaciones: {{ $base->observaciones }}</p>
@if($base->estado?->slug === 'devuelta' && $base->motivo_devolucion)
    <p><strong>Motivo de devolucion (supervisor):</strong> {{ $base->motivo_devolucion }}</p>
@endif

@php($esCerrado = $base->estado?->slug === 'cerrado')
@php($esSupervisor = auth()->user()?->role === 'supervisor')
@if($esCerrado && !$esSupervisor)
    <p><strong>Registro cerrado:</strong> no se puede editar. Si necesitas cambios, solicita reapertura al supervisor.</p>
@endif
@if($esCerrado && $esSupervisor)
    <h3>Reabrir / cambiar estado (Supervisor)</h3>
    <form method="post" action="{{ route('base-asignada.reabrir-contactado', $base->id) }}" style="margin-bottom:10px;">
        @csrf
        <button type="submit">Reabrir y llevar a Contactado</button>
    </form>
    <form method="post" action="{{ route('base-asignada.cambiar-estado-supervisor', $base->id) }}">
        @csrf
        <label>Nuevo estado</label>
        <select name="estado_id" required>
            @foreach($estados as $estado)
                <option value="{{ $estado->id }}">{{ $estado->nombre }}</option>
            @endforeach
        </select>
        <button type="submit">Guardar cambio de estado</button>
    </form>
@endif

<h3>Nueva gestion</h3>
@if(!$esCerrado || $esSupervisor)
<form method="post" action="{{ route('gestiones.store') }}">
    @csrf
    <input type="hidden" name="base_asignada_id" value="{{ $base->id }}">
    <label>Canal</label>
    <select name="tipo" required>
        @php($canalActual = old('tipo', 'llamada'))
        <option value="visita" @selected($canalActual === 'visita')>Visita</option>
        <option value="oficina" @selected($canalActual === 'oficina')>Oficina</option>
        <option value="llamada" @selected($canalActual === 'llamada')>Llamada</option>
        <option value="redes sociales" @selected($canalActual === 'redes sociales')>Redes sociales</option>
    </select>
    <label>Estado resultante</label>
    <select name="estado_id" id="estado_id">
        <option value="">No cambiar</option>
        @foreach($estados as $estado)
            <option value="{{ $estado->id }}" data-slug="{{ $estado->slug }}">{{ $estado->nombre }}</option>
        @endforeach
    </select>
    <label>Detalle de gestion</label>
    <textarea name="detalle" required>{{ old('detalle') }}</textarea>
    <label>Linea de credito</label>
    <select name="linea_credito">
        <option value="">No cambiar</option>
        @foreach($lineasCredito as $linea)
            <option value="{{ $linea }}" @selected(old('linea_credito', $base->linea_credito) === $linea)>{{ $linea }}</option>
        @endforeach
    </select>
    <label>Efectivo</label>
    <select name="efectivo" id="efectivo" disabled>
        <option value="">Seleccione</option>
        <option value="SI" @selected(old('efectivo') === 'SI')>SI</option>
        <option value="NO" @selected(old('efectivo') === 'NO')>NO</option>
    </select>
    <label>Monto linea de credito</label>
    <input type="number" id="monto_linea_credito" name="monto_linea_credito" min="0" step="1" value="{{ old('monto_linea_credito') }}" placeholder="Ej: 2000000" disabled>
    <label>Proxima gestion</label>
    <input type="datetime-local" name="proxima_gestion_at" value="{{ old('proxima_gestion_at') }}">
    <button type="submit">Registrar gestion</button>
</form>
@endif

<h3>Historial</h3>
<table>
    <thead><tr><th>Fecha</th><th>Registrado por</th><th>Tipo</th><th>Estado</th><th>Detalle</th></tr></thead>
    <tbody>
        @forelse($gestiones as $gestion)
            <tr>
                <td>{{ $gestion->created_at }}</td>
                <td>{{ $gestion->asesor?->name ?? 'N/A' }}</td>
                <td>{{ $gestion->tipo }}</td>
                <td>{{ $gestion->estado?->nombre }}</td>
                <td>{{ $gestion->detalle }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin gestiones.</td></tr>
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
                <td>{{ $h->lote_nombre ?? 'SIN LOTE' }}</td>
                <td>{{ $h->estado?->nombre ?? 'Sin estado' }}</td>
                <td>{{ $h->asesor?->name ?? 'Sin asignar' }}</td>
                <td>{{ is_null($h->monto_linea_credito) ? 'N/A' : '$' . number_format((float)$h->monto_linea_credito, 0, ',', '.') }}</td>
                <td><a href="{{ route('base-asignada.show', $h->id) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="6">No hay registros anteriores para esta cedula.</td></tr>
        @endforelse
    </tbody>
</table>
@else
<p>Este registro no tiene cedula, por eso no se puede consultar historico cruzado.</p>
@endif
<script>
    const estado = document.getElementById('estado_id');
    const efectivo = document.getElementById('efectivo');
    const monto = document.getElementById('monto_linea_credito');

    function syncCierreFields() {
        const slug = estado.options[estado.selectedIndex]?.dataset?.slug || '';
        const esCerrado = slug === 'cerrado';
        efectivo.disabled = !esCerrado;
        monto.disabled = !esCerrado;
        efectivo.required = esCerrado;
        monto.required = esCerrado;
        if (!esCerrado) {
            efectivo.value = '';
            monto.value = '';
        }
    }

    estado.addEventListener('change', syncCierreFields);
    syncCierreFields();
</script>
@endsection
