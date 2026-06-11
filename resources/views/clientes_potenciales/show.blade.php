@extends('layout')

@section('content')
<div class="actions" style="justify-content:space-between; align-items:center; margin-bottom:10px;">
    <h2 style="margin:0;">{{ $cliente->nombre }}</h2>
    <div class="actions">
        <button type="button" id="btn-open-datos-basicos">Editar datos</button>
        @if($cliente->estado?->slug === 'cerrado')
            <button type="button" id="btn-open-retomar">Retomar registro</button>
        @endif
    </div>
</div>
<table data-no-global-filters>
    <tbody>
        <tr>
            <th>Lote</th><td>{{ $cliente->lote_nombre ?: 'CLIENTE POTENCIAL' }}</td>
            <th>Estado actual</th><td><strong>{{ $cliente->estado?->nombre ?? 'Sin estado' }}</strong></td>
        </tr>
        <tr>
            <th>Comercial asignado</th><td>{{ $cliente->asesor?->name ?? 'N/A' }}</td>
            <th>Cedula</th><td>{{ $cliente->cedula ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Linea de credito</th><td>{{ $cliente->linea_credito ?? 'N/A' }}</td>
            <th>Efectivo</th><td>{{ is_null($cliente->efectivo) ? 'N/A' : ($cliente->efectivo ? 'SI' : 'NO') }}</td>
        </tr>
        <tr>
            <th>Monto solicitado</th><td>{{ is_null($cliente->monto_solicitado) ? 'N/A' : number_format((float)$cliente->monto_solicitado, 0, ',', '.') }}</td>
            <th>Monto aprobado</th><td>{{ is_null($cliente->monto_linea_credito) ? 'N/A' : number_format((float)$cliente->monto_linea_credito, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Origen</th><td>{{ $cliente->fuente ? ucfirst($cliente->fuente) : 'N/A' }}</td>
            <th>Estado desembolso</th><td>{{ $cliente->desembolso_estado ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Vinculacion cierre</th><td>{{ $vinculacionCierre ?? 'N/A' }}</td>
            <th>Producto cierre</th><td>{{ $productoCierre ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Desembolso solicitado</th><td>{{ $cliente->desembolso_estado_pendiente ? ($cliente->desembolso_estado_pendiente . ' (pendiente aprobacion)') : 'N/A' }}</td>
            <th>Empresa</th><td>{{ $cliente->empresa ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Telefono</th><td>{{ $cliente->telefono ?? 'N/A' }}</td>
            <th>Email</th><td>{{ $cliente->email ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Observaciones</th><td colspan="3">{{ $cliente->observaciones ?: 'N/A' }}</td>
        </tr>
    </tbody>
</table>
<div id="datos-basicos-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Editar datos basicos</h3>
            <button type="button" class="modal-close" id="btn-close-datos-basicos">Cerrar</button>
        </div>
        <form method="post" action="{{ route('clientes-potenciales.datos-basicos', $cliente->id) }}">
            @csrf
            <label>Nombre</label>
            <input name="nombre" value="{{ old('nombre', $cliente->nombre) }}" required>
            <label>Cedula</label>
            <input name="cedula" value="{{ old('cedula', $cliente->cedula) }}">
            <label>Telefono</label>
            <input name="telefono" value="{{ old('telefono', $cliente->telefono) }}">
            <label>Empresa</label>
            <input name="empresa" value="{{ old('empresa', $cliente->empresa) }}">
            <button type="submit">Guardar cambios</button>
        </form>
    </div>
</div>
@if($cliente->estado?->slug === 'cerrado')
<div id="retomar-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Retomar registro</h3>
            <button type="button" class="modal-close" id="btn-close-retomar">Cerrar</button>
        </div>
        <p>Se creara un cliente potencial nuevo asignado a ti, con estado Nuevo y origen Retomado.</p>
        <table data-no-global-filters>
            <tbody>
                <tr><th>Nombre</th><td>{{ $cliente->nombre }}</td></tr>
                <tr><th>Cedula</th><td>{{ $cliente->cedula ?? 'N/A' }}</td></tr>
                <tr><th>Telefono</th><td>{{ $cliente->telefono ?? 'N/A' }}</td></tr>
                <tr><th>Empresa</th><td>{{ $cliente->empresa ?? 'N/A' }}</td></tr>
                <tr><th>Email</th><td>{{ $cliente->email ?? 'N/A' }}</td></tr>
                <tr><th>Linea de credito</th><td>{{ $cliente->linea_credito ?? 'N/A' }}</td></tr>
                <tr><th>Observacion inicial</th><td>Registro retomado desde Cliente potencial #{{ $cliente->id }}.</td></tr>
            </tbody>
        </table>
        <form method="post" action="{{ route('clientes-potenciales.retomar', $cliente->id) }}">
            @csrf
            <button type="submit">Crear registro retomado</button>
        </form>
    </div>
</div>
@endif
@if($cliente->estado?->slug === 'devuelta' && $cliente->motivo_devolucion)
    <p><strong>Motivo de devolucion (supervisor):</strong> {{ $cliente->motivo_devolucion }}</p>
@endif

@php($esCerrado = $cliente->estado?->slug === 'cerrado')
@php($esEfectiva = $cliente->estado?->slug === 'efectiva')
@php($esPendienteSupervisor = $cliente->estado?->slug === 'pendiente-aprobacion-supervisor')
@if($esCerrado || $esEfectiva)
    <p><strong>Registro finalizado:</strong> no se permite ninguna modificacion adicional.</p>
@endif
@if($cliente->desembolso_motivo_devolucion)
    <p><strong>Ultima devolucion de desembolso:</strong> {{ $cliente->desembolso_motivo_devolucion }}</p>
@endif
@if($esPendienteSupervisor)
    <p><strong>Registro en proceso:</strong> esta en Pendiente de aprobacion (supervisor). No se puede modificar hasta que sea aprobado o devuelto.</p>
@endif
@php($esSupervisor = auth()->user()?->role === 'supervisor')
@if($esCerrado && (bool) $cliente->efectivo)
<h3>Estado de desembolso</h3>
<form method="post" action="{{ route('clientes-potenciales.desembolso.solicitar', $cliente->id) }}">
    @csrf
    <div class="inline-filters">
        <div class="field">
            <label>Nuevo estado desembolso</label>
            <select name="desembolso_estado" required @disabled((bool) $cliente->desembolso_estado_pendiente && !$esSupervisor)>
                @foreach($desembolsoEstados as $estadoDesembolso)
                    <option value="{{ $estadoDesembolso }}" @selected(old('desembolso_estado', $cliente->desembolso_estado) === $estadoDesembolso)>{{ ucfirst($estadoDesembolso) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Detalle</label>
            <input type="text" name="detalle" placeholder="Opcional" @disabled((bool) $cliente->desembolso_estado_pendiente && !$esSupervisor)>
        </div>
    </div>
    @if($cliente->desembolso_estado_pendiente && !$esSupervisor)
        <p><strong>Solicitud pendiente:</strong> {{ $cliente->desembolso_estado_pendiente }}. Debes esperar aprobacion del supervisor.</p>
    @else
        <button type="submit">{{ $esSupervisor ? 'Actualizar desembolso' : 'Solicitar cambio de desembolso' }}</button>
    @endif
</form>
@endif
<h3>Nueva gestion</h3>
@if(!$esCerrado && !$esEfectiva && !$esPendienteSupervisor)
<form method="post" action="{{ route('gestiones.store') }}">
    @csrf
    <input type="hidden" name="cliente_potencial_id" value="{{ $cliente->id }}">
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
                    <option value="{{ $linea }}" @selected(old('linea_credito', $cliente->linea_credito) === $linea)>{{ $linea }}</option>
                @endforeach
            </select>
        </div>
        <div class="field checkbox-field">
            <label>Vinculacion</label>
            <label class="checkbox-card">
                <input type="checkbox" name="es_vinculacion" id="es_vinculacion" value="1" @checked(old('es_vinculacion'))>
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
            <label>Linea de ahorro</label>
            <select name="linea_ahorro" id="linea_ahorro" disabled>
                <option value="">Seleccione</option>
                <option value="CDAT" @selected(old('linea_ahorro') === 'CDAT')>CDAT</option>
                <option value="AHORRO CONTRACTUAL" @selected(old('linea_ahorro') === 'AHORRO CONTRACTUAL')>AHORRO CONTRACTUAL</option>
            </select>
        </div>
        <div class="field">
            <label>Monto ahorro</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" id="monto_ahorro" name="monto_ahorro" value="{{ old('monto_ahorro') }}" placeholder="Ej: 500000" disabled>
        </div>
        <div class="field checkbox-field">
            <label>Asesoria comercial</label>
            <label class="checkbox-card">
                <input type="checkbox" name="es_asesoria_comercial" id="es_asesoria_comercial" value="1" @checked(old('es_asesoria_comercial')) disabled>
                <span>Es asesoria comercial</span>
            </label>
        </div>
        <div class="field">
            <label>Proxima gestion</label>
            <input type="datetime-local" name="proxima_gestion_at" value="{{ old('proxima_gestion_at') }}">
        </div>
        <div class="field">
            <label>Monto solicitado</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" id="monto_solicitado" name="monto_solicitado" value="{{ old('monto_solicitado', is_null($cliente->monto_solicitado) ? '' : (int)$cliente->monto_solicitado) }}">
        </div>
        <div class="field">
            <label>Tiempo invertido (min)</label>
            <input type="number" min="1" max="1440" step="1" name="minutos_invertidos" value="{{ old('minutos_invertidos') }}">
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
            <input type="text" inputmode="numeric" pattern="[0-9]*" id="monto_linea_credito" name="monto_linea_credito" value="{{ old('monto_linea_credito') }}" disabled>
        </div>
        <div class="field" style="display:none;">
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
    <thead><tr><th>Fecha</th><th>Registrado por</th><th>Tipo de gestion</th><th>Estado</th><th>Vinculacion</th><th>Asesoria</th><th>Producto</th><th>Proxima gestion</th><th>Tiempo invertido</th><th>Detalle</th></tr></thead>
    <tbody>
        @forelse($cliente->gestiones as $gestion)
            <tr>
                <td>{{ $gestion->created_at }}</td>
                <td>{{ $gestion->asesor?->name ?? 'N/A' }}</td>
                <td>{{ $gestion->tipo }}</td>
                <td>{{ $gestion->estado?->nombre }}</td>
                <td>{{ $gestion->es_vinculacion ? 'SI' : 'NO' }}</td>
                <td>{{ $gestion->es_asesoria_comercial ? 'SI' : 'NO' }}</td>
                <td>
                    @if($gestion->es_asesoria_comercial)
                        Asesoria comercial
                    @elseif($gestion->es_ahorro)
                        Ahorro: {{ $gestion->linea_ahorro ?? 'Sin linea' }} - ${{ number_format((float) ($gestion->monto_ahorro ?? 0), 0, ',', '.') }}
                    @elseif($gestion->es_vinculacion)
                        Credito: {{ $gestion->linea_credito_gestion ?? 'Sin linea' }}
                    @else
                        N/A
                    @endif
                </td>
                <td>{{ $gestion->proxima_gestion_at ? $gestion->proxima_gestion_at->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>{{ $gestion->minutos_invertidos ? ($gestion->minutos_invertidos . ' min') : 'N/A' }}</td>
                <td>{{ $gestion->detalle }}</td>
            </tr>
        @empty
            <tr><td colspan="10">Sin gestiones.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Historico asociados</h3>
@if(($historicoAsociados ?? collect())->isNotEmpty())
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Fecha carga</th>
            <th>Ultima modificacion</th>
            <th>Lote</th>
            <th>Nombre</th>
            <th>Cedula</th>
            <th>Estado actual</th>
            <th>Comercial</th>
            <th>Ver</th>
        </tr>
    </thead>
    <tbody>
        @foreach($historicoAsociados as $h)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($h->created_at)->format('Y-m-d H:i') }}</td>
                <td>{{ $h->ultima_gestion_at ? \Illuminate\Support\Carbon::parse($h->ultima_gestion_at)->format('Y-m-d H:i') : 'N/A' }}</td>
                <td>{{ $h->lote ?? 'SIN LOTE' }}</td>
                <td>{{ $h->nombre }}</td>
                <td>{{ $h->cedula ?? 'N/A' }}</td>
                <td>{{ $h->estado_nombre ?? 'Sin estado' }}</td>
                <td>{{ $h->asesor_nombre ?? 'Sin asignar' }}</td>
                <td>
                    @if(($h->tipo_registro ?? 'base') === 'cliente_potencial')
                        <a href="{{ route('clientes-potenciales.show', $h->registro_id) }}">Ver</a>
                    @else
                        <a href="{{ route('base-asignada.show', $h->registro_id) }}">Ver</a>
                    @endif
                </td>
            </tr>
        @endforeach
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
    const desembolsoEstado = document.getElementById('desembolso_estado');
    const ahorro = document.getElementById('es_ahorro');
    const vinculacion = document.getElementById('es_vinculacion');
    const asesoriaComercial = document.getElementById('es_asesoria_comercial');
    const lineaCredito = document.getElementById('linea_credito');
    const lineaAhorro = document.getElementById('linea_ahorro');
    const montoAhorro = document.getElementById('monto_ahorro');

    function syncCierreFields() {
        if (!estado || !efectivo || !monto) return;
        const slug = estado.options[estado.selectedIndex]?.dataset?.slug || '';
        const esCerrado = slug === 'cerrado';
        if (asesoriaComercial) {
            asesoriaComercial.disabled = !esCerrado;
            if (!esCerrado) asesoriaComercial.checked = false;
        }
        const esAsesoria = asesoriaComercial?.checked || false;
        if (esAsesoria) {
            efectivo.value = 'NO';
        }
        const esAhorro = ahorro?.checked || false;
        efectivo.disabled = !esCerrado;
        if (esAsesoria) efectivo.disabled = false;
        const esEfectivoSi = efectivo.value === 'SI';
        monto.disabled = esAsesoria || esAhorro || !esCerrado || !esEfectivoSi;
        if (desembolsoEstado) {
            desembolsoEstado.disabled = esAsesoria || esAhorro || !esCerrado || esEfectivoSi;
            desembolsoEstado.required = !esAsesoria && !esAhorro && esCerrado && !esEfectivoSi;
            if (esCerrado && esEfectivoSi && !esAsesoria && !esAhorro) {
                desembolsoEstado.value = 'Por desembolsar';
            }
        }
        efectivo.required = esCerrado;
        monto.required = !esAsesoria && !esAhorro && esCerrado && esEfectivoSi;
        if (esAsesoria || esAhorro || !esCerrado || !esEfectivoSi) monto.value = '';
        if (!esCerrado) efectivo.value = '';
        if (!esCerrado && desembolsoEstado) desembolsoEstado.value = '';
        if (esAhorro && desembolsoEstado) desembolsoEstado.value = '';
        if (esAsesoria && desembolsoEstado) desembolsoEstado.value = '';
    }

    function sanitizeMonto() {
        if (!monto) return;
        monto.value = (monto.value || '').replace(/\D+/g, '');
    }
    function sanitizeMontoSolicitado() {
        if (!montoSolicitado) return;
        montoSolicitado.value = (montoSolicitado.value || '').replace(/\D+/g, '');
    }
    function sanitizeMontoAhorro() {
        if (!montoAhorro) return;
        montoAhorro.value = (montoAhorro.value || '').replace(/\D+/g, '');
    }
    function syncProductoFields() {
        if (!ahorro || !lineaCredito) return;
        const esAsesoria = asesoriaComercial?.checked || false;
        if (esAsesoria) {
            if (ahorro) ahorro.checked = false;
            if (vinculacion) vinculacion.checked = false;
        }
        lineaCredito.disabled = esAsesoria || ahorro.checked;
        if (lineaAhorro) {
            lineaAhorro.disabled = esAsesoria || !ahorro.checked;
            lineaAhorro.required = !esAsesoria && ahorro.checked;
            if (lineaAhorro.disabled) lineaAhorro.value = '';
        }
        if (montoAhorro) {
            montoAhorro.disabled = esAsesoria || !ahorro.checked;
            montoAhorro.required = !esAsesoria && ahorro.checked;
            if (montoAhorro.disabled) montoAhorro.value = '';
        }
        if (montoSolicitado) {
            montoSolicitado.disabled = ahorro.checked;
        }
        if (esAsesoria || ahorro.checked) {
            lineaCredito.value = '';
            if (ahorro.checked && montoSolicitado) montoSolicitado.value = '';
        }
        if (ahorro) ahorro.disabled = esAsesoria;
        if (vinculacion) vinculacion.disabled = esAsesoria;
        syncCierreFields();
    }

    estado?.addEventListener('change', syncCierreFields);
    efectivo?.addEventListener('change', syncCierreFields);
    ahorro?.addEventListener('change', syncProductoFields);
    vinculacion?.addEventListener('change', syncProductoFields);
    asesoriaComercial?.addEventListener('change', syncProductoFields);
    monto?.addEventListener('input', sanitizeMonto);
    montoAhorro?.addEventListener('input', sanitizeMontoAhorro);
    montoSolicitado?.addEventListener('input', sanitizeMontoSolicitado);
    montoSolicitado?.closest('form')?.addEventListener('submit', sanitizeMontoSolicitado);
    monto?.closest('form')?.addEventListener('submit', sanitizeMonto);
    montoAhorro?.closest('form')?.addEventListener('submit', sanitizeMontoAhorro);
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
