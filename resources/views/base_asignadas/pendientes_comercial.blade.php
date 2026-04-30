@extends('layout')

@section('content')
<h2>Mis gestiones pendientes por aprobar</h2>
<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Estado</th>
            <th>Efectivo</th>
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
                <td>{{ is_null($base->efectivo) ? 'N/A' : ($base->efectivo ? 'SI' : 'NO') }}</td>
                <td>{{ is_null($base->monto_linea_credito) ? 'N/A' : number_format((float)$base->monto_linea_credito, 0, ',', '.') }}</td>
                <td><a href="{{ route('base-asignada.show', $base->id) }}">Ver detalle</a></td>
            </tr>
        @empty
            <tr><td colspan="7">No tienes gestiones pendientes por aprobar.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
