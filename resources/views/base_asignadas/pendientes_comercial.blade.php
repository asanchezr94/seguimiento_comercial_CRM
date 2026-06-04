@extends('layout')

@section('content')
<h2>Mis gestiones pendientes por aprobar</h2>
<table data-no-global-filters>
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
        @forelse($registros as $base)
            <tr>
                <td>{{ $base->lote }}</td>
                <td>{{ $base->cliente }}</td>
                <td>{{ $base->cedula ?? 'N/A' }}</td>
                <td>{{ $base->estado_nombre ?? 'N/A' }}</td>
                <td>{{ is_null($base->efectivo) ? 'N/A' : ($base->efectivo ? 'SI' : 'NO') }}</td>
                <td>{{ is_null($base->monto) ? 'N/A' : number_format((float)$base->monto, 0, ',', '.') }}</td>
                <td>
                    @if($base->tipo_registro === 'cliente_potencial')
                        <a href="{{ route('clientes-potenciales.show', $base->registro_id) }}">Ver detalle</a>
                    @else
                        <a href="{{ route('base-asignada.show', $base->registro_id) }}">Ver detalle</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No tienes gestiones pendientes por aprobar.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $registros->links() }}
@endsection
