@extends('layout')

@section('content')
<div class="actions" style="margin-bottom:12px;">
    <button type="button" id="btn-open-cliente-modal">+ + Cargar cliente potencial individual</button>
    <button type="button" id="btn-open-importar-clientes-modal">Cargar base potenciales masiva CSV</button>
</div>
<form method="get" action="{{ route('clientes-potenciales.index') }}" class="inline-filters">
    <div class="field">
        <label>Nombre o celular</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o celular">
    </div>
    <div class="field">
        <label>Estado</label>
        <select name="estado_id">
            <option value="">Todos</option>
            @foreach($estados as $estado)
                <option value="{{ $estado->id }}" @selected((string)request('estado_id') === (string)$estado->id)>{{ $estado->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Mes carga</label>
        <select name="mes">
            <option value="">Todos</option>
            @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected((string) request('mes') === (string) $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
            @endfor
        </select>
    </div>
    <div class="field">
        <label>Ano carga</label>
        <select name="anio">
            <option value="">Todos</option>
            @for($year = 2026; $year <= 2036; $year++)
                <option value="{{ $year }}" @selected((string) request('anio') === (string) $year)>{{ $year }}</option>
            @endfor
        </select>
    </div>
    <button type="submit">Filtrar</button>
    <a href="{{ route('clientes-potenciales.index') }}">Limpiar</a>
</form>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Nombre</th>
            <th>Cedula</th>
            <th>Linea de credito</th>
            <th>Empresa</th>
            <th>Telefono</th>
            <th>Estado</th>
            <th>Comercial actual</th>
            <th>Fecha asignacion</th>
            <th>Ultima modificacion</th>
            <th>Gestion</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($clientes as $cliente)
        <tr>
            <td>{{ $cliente->lote_nombre ?: 'CLIENTE POTENCIAL' }}</td>
            <td>{{ $cliente->nombre }}</td>
            <td>{{ $cliente->cedula ?? 'N/A' }}</td>
            <td>{{ $cliente->linea_credito ?? 'N/A' }}</td>
            <td>{{ $cliente->empresa }}</td>
            <td>{{ $cliente->telefono }}</td>
            <td>{{ $cliente->estado?->nombre ?? 'Sin estado' }}</td>
            <td>{{ $cliente->asesor?->name ?? 'Sin asignar' }}</td>
            <td>{{ $cliente->created_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
            <td>{{ ($cliente->ultima_gestion_at ?? $cliente->updated_at)?->format('Y-m-d H:i') ?? 'N/A' }}</td>
            <td><a class="btn-link" href="{{ route('clientes-potenciales.show', $cliente) }}">Gestionar</a></td>
        </tr>
        @empty
        <tr><td colspan="11">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $clientes->links() }}

@push('page-modals')
<div id="cliente-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Nuevo cliente potencial</h3>
            <button type="button" class="modal-close" id="btn-close-cliente-modal">Cerrar</button>
        </div>
        <form method="post" action="{{ route('clientes-potenciales.store') }}">
            @csrf
            @include('clientes_potenciales.form', ['cliente' => null])
            <button type="submit">Guardar</button>
        </form>
    </div>
</div>
<div id="importar-clientes-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Cargar clientes potenciales</h3>
            <button type="button" class="modal-close" id="btn-close-importar-clientes-modal">Cerrar</button>
        </div>
        <p>
            Formato permitido:
            <strong>nombre, telefono, cedula, linea_credito, email, empresa, fuente, observaciones, asesor_email</strong>.
            El <strong>nombre del lote</strong>, <strong>origen</strong> y <strong>observacion</strong> se definen para todo el cargue en este formulario; en el CSV son opcionales y pueden sobrescribir por fila. El estado se guarda automaticamente como <strong>Nuevo</strong>.
        </p>
        <p>
            <a class="btn-link" href="{{ route('clientes-potenciales.plantilla-csv') }}">Descargar plantilla CSV</a>
        </p>
        <form method="post" action="{{ route('clientes-potenciales.importar') }}" enctype="multipart/form-data">
            @csrf
            <label>Nombre del lote</label>
            <input type="text" name="lote_nombre" value="{{ old('lote_nombre') }}" placeholder="Ej: Potenciales junio 2026" required>
            <label>Origen</label>
            <select name="fuente" required>
                <option value="">Seleccione</option>
                @foreach($origenes as $origen)
                    <option value="{{ $origen }}" @selected(old('fuente') === $origen)>{{ ucfirst($origen) }}</option>
                @endforeach
            </select>
            <label>Observacion general</label>
            <textarea name="observaciones" required>{{ old('observaciones') }}</textarea>
            <label>Archivo CSV</label>
            <input type="file" name="archivo_csv" accept=".csv,.txt" required>
            <button type="submit">Cargar clientes</button>
        </form>
    </div>
</div>
@endpush

@push('page-scripts')
<script>
    (function () {
        const modal = document.getElementById('cliente-modal');
        const openBtn = document.getElementById('btn-open-cliente-modal');
        const closeBtn = document.getElementById('btn-close-cliente-modal');
        const importarModal = document.getElementById('importar-clientes-modal');
        const openImportarBtn = document.getElementById('btn-open-importar-clientes-modal');
        const closeImportarBtn = document.getElementById('btn-close-importar-clientes-modal');
        if (!modal || !openBtn || !closeBtn || !importarModal || !openImportarBtn || !closeImportarBtn) return;

        const open = (target) => {
            target.classList.add('open');
            target.setAttribute('aria-hidden', 'false');
        };
        const close = (target) => {
            target.classList.remove('open');
            target.setAttribute('aria-hidden', 'true');
        };

        openBtn.addEventListener('click', () => open(modal));
        closeBtn.addEventListener('click', () => close(modal));
        openImportarBtn.addEventListener('click', () => open(importarModal));
        closeImportarBtn.addEventListener('click', () => close(importarModal));
        [modal, importarModal].forEach((item) => {
            item.addEventListener('click', function (e) {
                if (e.target === item) close(item);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                close(modal);
                close(importarModal);
            }
        });
    })();
</script>
@endpush
@endsection
