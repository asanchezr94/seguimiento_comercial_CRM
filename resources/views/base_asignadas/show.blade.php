@extends('layout')

@section('content')
<div class="actions" style="justify-content:space-between; align-items:center; margin-bottom:10px;">
    <h2 style="margin:0;">{{ $base->nombre }}</h2>
    <div class="actions">
        @if($base->lote_uid)
            <a class="btn-link" href="{{ route('base-asignada.lote', ['loteRef' => $base->lote_uid]) }}">Volver al lote</a>
        @endif
        <button type="button" id="btn-open-datos-basicos">Editar datos</button>
        @if($base->estado?->slug === 'cerrado')
            <button type="button" id="btn-open-retomar">Retomar registro</button>
        @endif
        <a class="btn-link" href="{{ route('base-asignada.index') }}">Volver a base asignada</a>
    </div>
</div>
<table data-no-global-filters>
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
            <th>Estado desembolso</th><td>{{ $base->desembolso_estado ?? 'N/A' }}</td>
            <th>Desembolso solicitado</th><td>{{ $base->desembolso_estado_pendiente ? ($base->desembolso_estado_pendiente . ' (pendiente aprobacion)') : 'N/A' }}</td>
        </tr>
        <tr>
            <th>Vinculacion cierre</th><td>{{ $vinculacionCierre ?? 'N/A' }}</td>
            <th>Producto cierre</th><td>{{ $productoCierre ?? 'N/A' }}</td>
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
<div id="datos-basicos-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Editar datos basicos</h3>
            <button type="button" class="modal-close" id="btn-close-datos-basicos">Cerrar</button>
        </div>
        <form method="post" action="{{ route('base-asignada.datos-basicos', $base->id) }}">
            @csrf
            <label>Nombre</label>
            <input name="nombre" value="{{ old('nombre', $base->nombre) }}" required>
            <label>Cedula</label>
            <input name="cedula" value="{{ old('cedula', $base->cedula) }}">
            <label>Telefono</label>
            <input name="telefono" value="{{ old('telefono', $base->telefono) }}">
            <label>Empresa</label>
            <input name="empresa" value="{{ old('empresa', $base->empresa) }}">
            <button type="submit">Guardar cambios</button>
        </form>
    </div>
</div>
@if($base->estado?->slug === 'cerrado')
<div id="retomar-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Retomar registro</h3>
            <button type="button" class="modal-close" id="btn-close-retomar">Cerrar</button>
        </div>
        <p>Se creara un registro nuevo asignado a ti, con estado Nuevo y origen Retomado.</p>
        <table data-no-global-filters>
            <tbody>
                <tr><th>Nombre</th><td>{{ $base->nombre }}</td></tr>
                <tr><th>Cedula</th><td>{{ $base->cedula ?? 'N/A' }}</td></tr>
                <tr><th>Telefono</th><td>{{ $base->telefono ?? 'N/A' }}</td></tr>
                <tr><th>Empresa</th><td>{{ $base->empresa ?? 'N/A' }}</td></tr>
                <tr><th>Email</th><td>{{ $base->email ?? 'N/A' }}</td></tr>
                <tr><th>Linea de credito</th><td>{{ $base->linea_credito ?? 'N/A' }}</td></tr>
                <tr><th>Observacion inicial</th><td>Registro retomado desde Base asignada #{{ $base->id }}.</td></tr>
            </tbody>
        </table>
        <form method="post" action="{{ route('base-asignada.retomar', $base->id) }}">
            @csrf
            <button type="submit">Crear registro retomado</button>
        </form>
    </div>
</div>
@endif
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
@if($base->desembolso_motivo_devolucion)
    <p><strong>Ultima devolucion de desembolso:</strong> {{ $base->desembolso_motivo_devolucion }}</p>
@endif
@if($esPendienteSupervisor)
    <p><strong>Registro en proceso:</strong> esta en Pendiente de aprobacion (supervisor). No se puede modificar hasta que sea aprobado o devuelto.</p>
@endif
@if($esCerrado)
<h3>Estado de desembolso</h3>
<form method="post" action="{{ route('base-asignada.desembolso.solicitar', $base->id) }}">
    @csrf
    <div class="inline-filters">
        <div class="field">
            <label>Nuevo estado desembolso</label>
            <select name="desembolso_estado" required @disabled((bool) $base->desembolso_estado_pendiente && !$esSupervisor)>
                @foreach($desembolsoEstados as $estadoDesembolso)
                    <option value="{{ $estadoDesembolso }}" @selected(old('desembolso_estado', $base->desembolso_estado) === $estadoDesembolso)>{{ ucfirst($estadoDesembolso) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Detalle</label>
            <input type="text" name="detalle" placeholder="Opcional" @disabled((bool) $base->desembolso_estado_pendiente && !$esSupervisor)>
        </div>
    </div>
    @if($base->desembolso_estado_pendiente && !$esSupervisor)
        <p><strong>Solicitud pendiente:</strong> {{ $base->desembolso_estado_pendiente }}. Debes esperar aprobacion del supervisor.</p>
    @else
        <button type="submit">{{ $esSupervisor ? 'Actualizar desembolso' : 'Solicitar cambio de desembolso' }}</button>
    @endif
</form>
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
                @php($canalActual = old('tipo', 'ninguna'))
                <option value="ninguna" @selected($canalActual === 'ninguna')>Ninguna</option>
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
            <select name="linea_credito" id="linea_credito">
                <option value="">No cambiar</option>
                @foreach($lineasCredito as $linea)
                    <option value="{{ $linea }}" @selected(old('linea_credito', $base->linea_credito) === $linea)>{{ $linea }}</option>
                @endforeach
            </select>
        </div>
        <div class="field checkbox-field">
            <label>Vinculacion</label>
            <label class="checkbox-card">
                <input type="checkbox" name="es_vinculacion" value="1" @checked(old('es_vinculacion'))>
                <span>Marcar como vinculacion</span>
            </label>
        </div>
        <div class="field checkbox-field">
            <label>Ahorro</label>
            <label class="checkbox-card">
                <input type="checkbox" name="es_ahorro" id="es_ahorro" value="1" @checked(old('es_ahorro'))>
                <span>Es ahorro</span>
            </label>
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
        <div class="field">
            <label>Estado desembolso</label>
            <select name="desembolso_estado" id="desembolso_estado" disabled>
                <option value="">Seleccione</option>
                @foreach($desembolsoEstados as $estadoDesembolso)
                    <option value="{{ $estadoDesembolso }}" @selected(old('desembolso_estado') === $estadoDesembolso)>{{ ucfirst($estadoDesembolso) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <button type="submit">Registrar gestion</button>
</form>
@endif

<h3>Historial</h3>
<table data-no-global-filters>
    <thead><tr><th>Fecha</th><th>Registrado por</th><th>Tipo de gestion</th><th>Estado</th><th>Vinculacion</th><th>Producto</th><th>Proxima gestion</th><th>Tiempo invertido</th><th>Detalle</th></tr></thead>
    <tbody>
        @forelse($gestiones as $gestion)
            <tr>
                <td>{{ $gestion->created_at }}</td>
                <td>{{ $gestion->asesor?->name ?? 'N/A' }}</td>
                <td>{{ $gestion->tipo }}</td>
                <td>{{ $gestion->estado?->nombre }}</td>
                <td>{{ $gestion->es_vinculacion ? 'SI' : 'NO' }}</td>
                <td>
                    @if($gestion->es_vinculacion)
                        {{ $gestion->es_ahorro ? 'Ahorro' : 'Credito: ' . ($gestion->linea_credito_gestion ?? 'Sin linea') }}
                    @else
                        N/A
                    @endif
                </td>
                <td>{{ $gestion->proxima_gestion_at ? $gestion->proxima_gestion_at->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>{{ $gestion->minutos_invertidos ? ($gestion->minutos_invertidos . ' min') : 'N/A' }}</td>
                <td>{{ $gestion->detalle }}</td>
            </tr>
        @empty
            <tr><td colspan="9">Sin gestiones.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $gestiones->links() }}

<h3>Historico asociados</h3>
<table data-no-global-filters>
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
            <tr><td colspan="7">No hay registros relacionados por cedula, telefono o nombre.</td></tr>
        @endforelse
    </tbody>
</table>
<script>
    const estado = document.getElementById('estado_id');
    const efectivo = document.getElementById('efectivo');
    const montoSolicitado = document.getElementById('monto_solicitado');
    const monto = document.getElementById('monto_linea_credito');
    const desembolsoEstado = document.getElementById('desembolso_estado');
    const ahorro = document.getElementById('es_ahorro');
    const lineaCredito = document.getElementById('linea_credito');

    function syncCierreFields() {
        if (!estado || !efectivo || !monto) return;
        const slug = estado.options[estado.selectedIndex]?.dataset?.slug || '';
        const esCerrado = slug === 'cerrado';
        const esAhorro = ahorro?.checked || false;
        efectivo.disabled = !esCerrado;
        const esEfectivoSi = efectivo.value === 'SI';
        monto.disabled = esAhorro || !esCerrado || !esEfectivoSi;
        if (desembolsoEstado) {
            desembolsoEstado.disabled = esAhorro || !esCerrado;
            desembolsoEstado.required = !esAhorro && esCerrado;
        }
        efectivo.required = esCerrado;
        monto.required = !esAhorro && esCerrado && esEfectivoSi;
        if (esAhorro || !esCerrado || !esEfectivoSi) {
            monto.value = '';
        }
        if (!esCerrado) {
            efectivo.value = '';
            if (desembolsoEstado) desembolsoEstado.value = '';
        }
        if (esAhorro && desembolsoEstado) {
            desembolsoEstado.value = '';
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
    function syncProductoFields() {
        if (!ahorro || !lineaCredito) return;
        lineaCredito.disabled = ahorro.checked;
        if (montoSolicitado) {
            montoSolicitado.disabled = ahorro.checked;
        }
        if (ahorro.checked) {
            lineaCredito.value = '';
            if (montoSolicitado) montoSolicitado.value = '';
        }
        syncCierreFields();
    }

    estado?.addEventListener('change', syncCierreFields);
    efectivo?.addEventListener('change', syncCierreFields);
    ahorro?.addEventListener('change', syncProductoFields);
    monto?.addEventListener('input', sanitizeMonto);
    montoSolicitado?.addEventListener('input', sanitizeMontoSolicitado);
    montoSolicitado?.closest('form')?.addEventListener('submit', sanitizeMontoSolicitado);
    monto?.closest('form')?.addEventListener('submit', sanitizeMonto);
    syncCierreFields();
    syncProductoFields();
    (function () {
        const modalDatos = document.getElementById('datos-basicos-modal');
        const openDatos = document.getElementById('btn-open-datos-basicos');
        const closeDatos = document.getElementById('btn-close-datos-basicos');
        if (!modalDatos || !openDatos || !closeDatos) return;
        const open = () => {
            modalDatos.classList.add('open');
            modalDatos.setAttribute('aria-hidden', 'false');
        };
        const close = () => {
            modalDatos.classList.remove('open');
            modalDatos.setAttribute('aria-hidden', 'true');
        };
        openDatos.addEventListener('click', open);
        closeDatos.addEventListener('click', close);
        modalDatos.addEventListener('click', (e) => {
            if (e.target === modalDatos) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    })();
    (function () {
        const modalRetomar = document.getElementById('retomar-modal');
        const openRetomar = document.getElementById('btn-open-retomar');
        const closeRetomar = document.getElementById('btn-close-retomar');
        if (!modalRetomar || !openRetomar || !closeRetomar) return;
        const open = () => {
            modalRetomar.classList.add('open');
            modalRetomar.setAttribute('aria-hidden', 'false');
        };
        const close = () => {
            modalRetomar.classList.remove('open');
            modalRetomar.setAttribute('aria-hidden', 'true');
        };
        openRetomar.addEventListener('click', open);
        closeRetomar.addEventListener('click', close);
        modalRetomar.addEventListener('click', (e) => {
            if (e.target === modalRetomar) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    })();
</script>
@endsection
