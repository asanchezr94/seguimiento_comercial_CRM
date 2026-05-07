@extends('layout')

@section('content')
<h2>Mis registros cerrados</h2>
<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Linea credito</th>
            <th>Monto</th>
            <th>Estado</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bases as $base)
            <tr>
                <td>{{ $base->lote_nombre }}</td>
                <td>{{ $base->nombre }}</td>
                <td>{{ $base->cedula ?? 'N/A' }}</td>
                <td>{{ $base->linea_credito ?? 'N/A' }}</td>
                <td>{{ is_null($base->monto_linea_credito) ? 'N/A' : number_format((float)$base->monto_linea_credito, 0, ',', '.') }}</td>
                <td>{{ $base->estado?->nombre ?? 'N/A' }}</td>
                <td><a href="{{ route('base-asignada.show', $base->id) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="7">No tienes registros cerrados.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $bases->links() }}
@endsection
