@extends('layout')

@section('content')
<h2>Lote: {{ $loteNombre }}</h2>
<p>Total registros del lote: <strong>{{ $totalRegistrosLote }}</strong></p>
<p>Registros visibles en esta pagina: <strong>{{ $bases->count() }}</strong></p>

<h3>Filtros</h3>
<form method="get" action="{{ route('base-asignada.lote', ['loteRef' => $loteUid]) }}">
    <label>Estado</label>
    <select name="estado_id">
        <option value="">Todos</option>
        @foreach($estadosFiltro as $estado)
            <option value="{{ $estado->id }}" @selected(request('estado_id') == $estado->id)>{{ $estado->nombre }}</option>
        @endforeach
    </select>
    <label>Nombre o cedula</label>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o cedula">
    <button type="submit">Filtrar</button>
    <a href="{{ route('base-asignada.lote', ['loteRef' => $loteUid]) }}">Limpiar</a>
</form>

@if(auth()->user()?->role === 'supervisor')
<h3>Asignacion por lote</h3>
<p>Solo se reasignan/desasignan registros sin gestion (0%). Los ya gestionados no se tocan.</p>
<p>Disponibles sin gestion: <strong>{{ $totalSinGestion }}</strong></p>
<form method="post" action="{{ route('base-asignada.lote.asignar', ['loteRef' => $loteUid]) }}">
    @csrf
    <label>Comerciales</label>
    <div style="border:1px solid #ddd; padding:10px; border-radius:6px; max-height:220px; overflow:auto;">
        <label style="display:flex; align-items:center; gap:8px; margin:0 0 8px 0;">
            <input type="checkbox" name="comerciales[]" value="no_asignar" style="width:auto;">
            <span>No asignar</span>
        </label>
        @foreach($comerciales as $comercial)
            <label style="display:flex; align-items:center; gap:8px; margin:0 0 8px 0;">
                <input type="checkbox" name="comerciales[]" value="{{ $comercial->id }}" style="width:auto;">
                <span>{{ $comercial->name }}</span>
            </label>
        @endforeach
    </div>
    <small>Selecciona uno o varios destinos. No necesitas usar Ctrl.</small>
    <button type="submit">Asignar lote</button>
</form>
@endif

<h3>Detalle del lote</h3>
<table>
    <thead>
        <tr>
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
        @forelse($bases as $base)
            <tr>
                <td>{{ $base->nombre }}</td>
                <td>{{ $base->cedula ?? 'N/A' }}</td>
                <td>{{ $base->linea_credito ?? 'N/A' }}</td>
                <td>{{ $base->empresa }}</td>
                <td>{{ $base->telefono }}</td>
                <td>{{ $base->estado?->nombre ?? 'Sin estado' }}</td>
                <td>{{ $base->asesor?->name ?? 'Sin asignar' }}</td>
                <td>{{ $base->asignado_at ? $base->asignado_at->format('Y-m-d H:i') : 'N/A' }}</td>
                <td>{{ $base->ultima_gestion_at ? $base->ultima_gestion_at->format('Y-m-d H:i') : 'N/A' }}</td>
                <td><a href="{{ route('base-asignada.show', $base) }}">Gestionar</a></td>
            </tr>
        @empty
            <tr><td colspan="10">Sin registros en este lote.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $bases->links() }}
@endsection
