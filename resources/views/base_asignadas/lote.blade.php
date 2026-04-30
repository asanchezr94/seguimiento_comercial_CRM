@extends('layout')

@section('content')
<h2>Lote: {{ $loteNombre }}</h2>
<p>Total registros: {{ $bases->count() }}</p>

<h3>Filtros</h3>
<form method="get" action="{{ route('base-asignada.lote', ['loteNombre' => $loteNombre]) }}">
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
    <a href="{{ route('base-asignada.lote', ['loteNombre' => $loteNombre]) }}">Limpiar</a>
</form>

@if(auth()->user()?->role === 'supervisor')
<h3>Asignacion por lote</h3>
<p>Solo se reasignan/desasignan registros sin gestion (0%). Los ya gestionados no se tocan.</p>
<p>Disponibles sin gestion: <strong>{{ $totalSinGestion }}</strong></p>
<form method="post" action="{{ route('base-asignada.lote.asignar', ['loteNombre' => $loteNombre]) }}">
    @csrf
    <label>Comerciales</label>
    <select name="comerciales[]" multiple size="6" required>
        <option value="no_asignar">No asignar</option>
        @foreach($comerciales as $comercial)
            <option value="{{ $comercial->id }}">{{ $comercial->name }} ({{ $comercial->email }})</option>
        @endforeach
    </select>
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
                <td><a href="{{ route('base-asignada.show', $base) }}">Gestionar</a></td>
            </tr>
        @empty
            <tr><td colspan="8">Sin registros en este lote.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
