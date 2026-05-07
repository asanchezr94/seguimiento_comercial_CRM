@extends('layout')

@section('content')
<h2>Gestion de {{ $comercial->name }}</h2>

<form method="get" action="{{ route('supervisor.comerciales.gestion', $comercial->id) }}">
    <label>Estado</label>
    <select name="estado_id">
        <option value="">Todos los estados</option>
        @foreach($estados as $estado)
            <option value="{{ $estado->id }}" @selected(request('estado_id') == $estado->id)>{{ $estado->nombre }}</option>
        @endforeach
    </select>
    <label>Buscar (lote, nombre o cedula)</label>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar...">
    <button type="submit">Filtrar</button>
    <a href="{{ route('supervisor.comerciales.gestion', $comercial->id) }}">Limpiar</a>
</form>

<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Estado</th>
            <th>Linea credito</th>
            <th>Monto</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bases as $base)
            <tr>
                <td>{{ $base->lote_nombre }}</td>
                <td>{{ $base->nombre }}</td>
                <td>{{ $base->cedula ?? 'N/A' }}</td>
                <td>{{ $base->estado?->nombre ?? 'N/A' }}</td>
                <td>{{ $base->linea_credito ?? 'N/A' }}</td>
                <td>{{ is_null($base->monto_linea_credito) ? 'N/A' : number_format((float)$base->monto_linea_credito, 0, ',', '.') }}</td>
                <td><a href="{{ route('base-asignada.show', $base->id) }}">Gestionar</a></td>
            </tr>
        @empty
            <tr><td colspan="7">Sin registros para este filtro.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $bases->links() }}
@endsection
