@extends('layout')

@section('content')
<h2>Gestiones pendientes por aprobar</h2>
<style>
    .acciones-pendiente {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
    }
    .acciones-pendiente .motivo-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Comercial</th>
            <th>Estado</th>
            <th>Efectivo</th>
            <th>Monto</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bases as $base)
            <tr>
                <td>{{ $base->lote_nombre }}</td>
                <td>{{ $base->nombre }}</td>
                <td>{{ $base->cedula }}</td>
                <td>{{ $base->asesor?->name }}</td>
                <td>{{ $base->estado?->nombre ?? 'N/A' }}</td>
                <td>{{ is_null($base->efectivo) ? 'N/A' : ($base->efectivo ? 'SI' : 'NO') }}</td>
                <td>{{ is_null($base->monto_linea_credito) ? 'N/A' : number_format((float)$base->monto_linea_credito, 0, ',', '.') }}</td>
                <td>
                    <div class="acciones-pendiente">
                    <a class="btn-link" href="{{ route('base-asignada.show', $base->id) }}">Gestionar</a>
                    <form method="post" action="{{ route('base-asignada.pendientes.aprobar', $base->id) }}" class="inline">
                        @csrf
                        <button type="submit">Aprobar</button>
                    </form>
                    <button type="button" class="btn-open-devolver" data-action="{{ route('base-asignada.pendientes.devolver', $base->id) }}">Devolver</button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8">No hay gestiones pendientes.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $bases->links() }}

<h3>Clientes potenciales pendientes</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Comercial</th>
            <th>Estado</th>
            <th>Efectivo</th>
            <th>Monto</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($clientesPendientes ?? collect()) as $cliente)
            <tr>
                <td>{{ $cliente->nombre }}</td>
                <td>{{ $cliente->cedula }}</td>
                <td>{{ $cliente->asesor?->name }}</td>
                <td>{{ $cliente->estado?->nombre ?? 'N/A' }}</td>
                <td>{{ is_null($cliente->efectivo) ? 'N/A' : ($cliente->efectivo ? 'SI' : 'NO') }}</td>
                <td>{{ is_null($cliente->monto_linea_credito) ? 'N/A' : number_format((float)$cliente->monto_linea_credito, 0, ',', '.') }}</td>
                <td>
                    <div class="acciones-pendiente">
                    <a class="btn-link" href="{{ route('clientes-potenciales.show', $cliente->id) }}">Gestionar</a>
                    <form method="post" action="{{ route('clientes-potenciales.pendientes.aprobar', $cliente->id) }}" class="inline">
                        @csrf
                        <button type="submit">Aprobar</button>
                    </form>
                    <button type="button" class="btn-open-devolver" data-action="{{ route('clientes-potenciales.pendientes.devolver', $cliente->id) }}">Devolver</button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No hay clientes potenciales pendientes.</td></tr>
        @endforelse
    </tbody>
</table>
{{ ($clientesPendientes ?? null)?->links() }}

<h3>Desembolsos pendientes por aprobar - Base asignada</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Comercial</th>
            <th>Actual</th>
            <th>Solicitado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($basesDesembolsoPendientes ?? collect()) as $base)
            <tr>
                <td>{{ $base->lote_nombre }}</td>
                <td>{{ $base->nombre }}</td>
                <td>{{ $base->cedula }}</td>
                <td>{{ $base->asesor?->name }}</td>
                <td>{{ $base->desembolso_estado ?? 'N/A' }}</td>
                <td>{{ $base->desembolso_estado_pendiente }}</td>
                <td>
                    <div class="acciones-pendiente">
                        <a class="btn-link" href="{{ route('base-asignada.show', $base->id) }}">Gestionar</a>
                        <form method="post" action="{{ route('base-asignada.desembolso.aprobar', $base->id) }}" class="inline">
                            @csrf
                            <button type="submit">Aprobar</button>
                        </form>
                        <button type="button" class="btn-open-devolver" data-action="{{ route('base-asignada.desembolso.devolver', $base->id) }}">Devolver</button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No hay desembolsos pendientes de base asignada.</td></tr>
        @endforelse
    </tbody>
</table>
{{ ($basesDesembolsoPendientes ?? null)?->links() }}

<h3>Desembolsos pendientes por aprobar - Clientes potenciales</h3>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Comercial</th>
            <th>Actual</th>
            <th>Solicitado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($clientesDesembolsoPendientes ?? collect()) as $cliente)
            <tr>
                <td>{{ $cliente->nombre }}</td>
                <td>{{ $cliente->cedula }}</td>
                <td>{{ $cliente->asesor?->name }}</td>
                <td>{{ $cliente->desembolso_estado ?? 'N/A' }}</td>
                <td>{{ $cliente->desembolso_estado_pendiente }}</td>
                <td>
                    <div class="acciones-pendiente">
                        <a class="btn-link" href="{{ route('clientes-potenciales.show', $cliente->id) }}">Gestionar</a>
                        <form method="post" action="{{ route('clientes-potenciales.desembolso.aprobar', $cliente->id) }}" class="inline">
                            @csrf
                            <button type="submit">Aprobar</button>
                        </form>
                        <button type="button" class="btn-open-devolver" data-action="{{ route('clientes-potenciales.desembolso.devolver', $cliente->id) }}">Devolver</button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No hay desembolsos pendientes de clientes potenciales.</td></tr>
        @endforelse
    </tbody>
</table>
{{ ($clientesDesembolsoPendientes ?? null)?->links() }}

<div id="modal-devolver" class="modal-backdrop" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Devolver gestion</h3>
            <button type="button" class="modal-close" id="btn-close-devolver">Cerrar</button>
        </div>
        <form method="post" id="form-devolver-modal" action="">
            @csrf
            <label>Motivo de devolucion</label>
            <textarea name="motivo_devolucion" placeholder="Escribe el motivo..." required></textarea>
            <button type="submit">Confirmar devolucion</button>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('modal-devolver');
        const closeBtn = document.getElementById('btn-close-devolver');
        const form = document.getElementById('form-devolver-modal');
        const openButtons = document.querySelectorAll('.btn-open-devolver');
        if (!modal || !closeBtn || !form || openButtons.length === 0) return;

        const open = (action) => {
            form.setAttribute('action', action);
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        };
        const close = () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        };

        openButtons.forEach((btn) => {
            btn.addEventListener('click', () => open(btn.dataset.action));
        });
        closeBtn.addEventListener('click', close);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    })();
</script>
@endsection
