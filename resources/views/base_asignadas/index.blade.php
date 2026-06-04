@extends('layout')

@section('content')
@if($isSupervisor)
<div class="actions" style="margin-bottom:12px; flex-wrap:nowrap;">
    <button type="button" id="btn-open-manual">+ Cargar cliente individual</button>
    <button type="button" id="btn-open-csv">Cargar base masiva (CSV)</button>
</div>
@endif

<h3>Lotes</h3>
<form method="get" action="{{ route('base-asignada.index') }}" class="inline-filters">
    <div class="field">
        <label>Nombre del lote</label>
        <input type="text" name="lote" value="{{ request('lote') }}" placeholder="Nombre del lote">
    </div>
    <div class="field">
        <label>Origen</label>
        <select name="origen">
            <option value="">Todos</option>
            <option value="llamada" @selected(request('origen') === 'llamada')>Llamada</option>
            <option value="visita" @selected(request('origen') === 'visita')>Visita</option>
            <option value="oficina" @selected(request('origen') === 'oficina')>Oficina</option>
            <option value="redes sociales" @selected(request('origen') === 'redes sociales')>Redes sociales</option>
            <option value="base interna" @selected(request('origen') === 'base interna')>Base interna</option>
            <option value="referidos" @selected(request('origen') === 'referidos')>Referidos</option>
        </select>
    </div>
    <button type="submit">Filtrar</button>
    <a href="{{ route('base-asignada.index') }}">Limpiar</a>
</form>
<table data-no-global-filters>
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
@push('page-modals')
<div id="manual-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Nueva base asignada</h3>
            <button type="button" class="modal-close" id="btn-close-manual">Cerrar</button>
        </div>
        <form method="post" action="{{ route('base-asignada.store') }}">
            @csrf
            @include('base_asignadas.form', ['base' => null])
            <button type="submit">Guardar</button>
        </form>
    </div>
</div>

<div id="csv-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Carga masiva CSV</h3>
            <button type="button" class="modal-close" id="btn-close-csv">Cerrar</button>
        </div>
        <p>Columnas obligatorias en CSV: <code>nombre,telefono</code>. El estado se guarda automaticamente como <strong>Nuevo</strong>.</p>
        <p><a class="btn-link" href="{{ route('base-asignada.plantilla-csv') }}">Descargar plantilla CSV</a></p>
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
            <label>Observacion general</label>
            <textarea name="observaciones" required>{{ old('observaciones') }}</textarea>
            <input type="file" name="archivo_csv" accept=".csv,.txt" required>
            <button type="submit">Importar</button>
        </form>
    </div>
</div>
@endpush

@push('page-scripts')
<script>
    (function () {
        const manualModal = document.getElementById('manual-modal');
        const openManualBtn = document.getElementById('btn-open-manual');
        const closeManualBtn = document.getElementById('btn-close-manual');
        const modal = document.getElementById('csv-modal');
        const openBtn = document.getElementById('btn-open-csv');
        const closeBtn = document.getElementById('btn-close-csv');
        if (!manualModal || !openManualBtn || !closeManualBtn || !modal || !openBtn || !closeBtn) return;

        const openManual = () => {
            manualModal.classList.add('open');
            manualModal.setAttribute('aria-hidden', 'false');
        };
        const closeManual = () => {
            manualModal.classList.remove('open');
            manualModal.setAttribute('aria-hidden', 'true');
        };

        const open = () => {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        };
        const close = () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        };

        openManualBtn.addEventListener('click', openManual);
        closeManualBtn.addEventListener('click', closeManual);
        manualModal.addEventListener('click', function (e) {
            if (e.target === manualModal) closeManual();
        });

        openBtn.addEventListener('click', open);
        closeBtn.addEventListener('click', close);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                close();
                closeManual();
            }
        });
    })();
</script>
@endpush
@endif
@endsection
