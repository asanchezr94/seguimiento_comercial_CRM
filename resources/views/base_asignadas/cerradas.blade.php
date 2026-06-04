@extends('layout')

@section('content')
<h2>Mis registros cerrados</h2>
<form method="get" action="{{ route('base-asignada.cerradas') }}" class="inline-filters">
    <div class="field">
        <label>Nombre o celular</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o celular">
    </div>
    <div class="field">
        <label>Estado</label>
        <select name="estado_id">
            <option value="">Todos</option>
            @foreach(($estadosFiltro ?? []) as $estado)
                <option value="{{ $estado->id }}" @selected((string)request('estado_id') === (string)$estado->id)>{{ $estado->nombre }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit">Filtrar</button>
    <a href="{{ route('base-asignada.cerradas') }}">Limpiar</a>
</form>
<table data-no-global-filters>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Linea credito</th>
            <th>Monto solicitado</th>
            <th>Monto aprobado</th>
            <th>Fecha asignacion</th>
            <th>Ultima modificacion</th>
            <th>Estado</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($registros as $base)
            <tr>
                <td>{{ $base->lote }}</td>
                <td>{{ $base->cliente }}</td>
                <td>{{ $base->cedula ?? 'N/A' }}</td>
                <td>{{ $base->linea_credito ?? 'N/A' }}</td>
                <td>{{ is_null($base->monto_solicitado) ? 'N/A' : number_format((float)$base->monto_solicitado, 0, ',', '.') }}</td>
                <td>{{ is_null($base->monto_aprobado) ? 'N/A' : number_format((float)$base->monto_aprobado, 0, ',', '.') }}</td>
                <td>{{ $base->fecha_asignacion ? \Illuminate\Support\Carbon::parse($base->fecha_asignacion)->format('Y-m-d H:i') : 'N/A' }}</td>
                <td>{{ $base->ultima_modificacion ? \Illuminate\Support\Carbon::parse($base->ultima_modificacion)->format('Y-m-d H:i') : 'N/A' }}</td>
                <td>{{ $base->estado_nombre ?? 'N/A' }}</td>
                <td>
                    @if($base->tipo_registro === 'cliente_potencial')
                        <a href="{{ route('clientes-potenciales.show', $base->registro_id) }}">Ver</a>
                    @else
                        <a href="{{ route('base-asignada.show', $base->registro_id) }}">Ver</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10">No tienes registros cerrados.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $registros->links() }}
@endsection
