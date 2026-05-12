@extends('layout')

@section('content')
@php($isSupervisor = auth()->user()?->role === 'supervisor')
@if($isSupervisor)
<p><a href="{{ route('base-asignada.create') }}">+ Nueva base asignada</a></p>
<p><button type="button" id="btn-open-csv">Cargar base (CSV)</button></p>
@endif

<h3>Lotes</h3>
<form method="get" action="{{ route('base-asignada.index') }}">
    <label>Buscar lote</label>
    <input type="text" name="lote" value="{{ request('lote') }}" placeholder="Nombre del lote">
    <button type="submit">Filtrar</button>
    <a href="{{ route('base-asignada.index') }}">Limpiar</a>
</form>
<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Origen</th>
            <th>Fecha carga</th>
            <th>Comerciales asignados</th>
            <th>Ultima modificacion</th>
            <th>Total registros</th>
            <th>% gestion</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lotes as $lote)
        <tr>
            <td>{{ $lote->lote_nombre }}</td>
            <td>{{ $lote->origen_base ? ucfirst($lote->origen_base) : 'N/A' }}</td>
            <td>{{ $lote->fecha_carga ? \Illuminate\Support\Carbon::parse($lote->fecha_carga)->format('d/m/Y H:i') : 'N/A' }}</td>
            <td>{{ $lote->comerciales_asignados ?: 'Sin asignar' }}</td>
            <td>{{ $lote->ultima_modificacion ? \Illuminate\Support\Carbon::parse($lote->ultima_modificacion)->format('d/m/Y H:i') : 'N/A' }}</td>
            <td>{{ $lote->total }}</td>
            <td>{{ number_format((float) $lote->porcentaje_gestion, 1) }}%</td>
            <td class="actions">
                <a href="{{ route('base-asignada.lote', ['loteRef' => $lote->lote_uid]) }}">Ver lote</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="8">Sin lotes.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $lotes->links() }}

@if($isSupervisor)
<div id="csv-modal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Carga masiva CSV</h3>
            <button type="button" class="modal-close" id="btn-close-csv">Cerrar</button>
        </div>
        <p>Columnas obligatorias en CSV: <code>nombre,telefono</code></p>
        <p>Columnas opcionales: <code>cedula,linea_credito,email,empresa,observaciones,estado_slug,comercial_email</code></p>
        <p><strong>Nota:</strong> <code>comercial_email</code> es opcional; si lo dejas vacio, entra sin asignar para repartir luego por lote.</p>
        <form method="post" action="{{ route('base-asignada.importar') }}" enctype="multipart/form-data">
            @csrf
            <label>Nombre del lote/base</label>
            <input type="text" name="lote_nombre" value="{{ old('lote_nombre') }}" placeholder="Ej: ARA Mayo 2026" required>
            <label>Origen</label>
            <select name="origen" required>
                <option value="">Seleccione</option>
                <option value="llamada" @selected(old('origen') === 'llamada')>Llamada</option>
                <option value="visita" @selected(old('origen') === 'visita')>Visita</option>
                <option value="oficina" @selected(old('origen') === 'oficina')>Oficina</option>
                <option value="redes sociales" @selected(old('origen') === 'redes sociales')>Redes sociales</option>
                <option value="base interna" @selected(old('origen') === 'base interna')>Base interna</option>
                <option value="referidos" @selected(old('origen') === 'referidos')>Referidos</option>
            </select>
            <input type="file" name="archivo_csv" accept=".csv,.txt" required>
            <button type="submit">Importar</button>
        </form>
    </div>
</div>
<script>
    (function () {
        const modal = document.getElementById('csv-modal');
        const openBtn = document.getElementById('btn-open-csv');
        const closeBtn = document.getElementById('btn-close-csv');
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
@endif
@endsection
