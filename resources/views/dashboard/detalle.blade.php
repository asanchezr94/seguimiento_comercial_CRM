@extends('layout')

@section('content')
<h2>Detalle dashboard: {{ ucfirst(str_replace('_', ' ', $tipo)) }}</h2>
<p>Periodo: <strong>{{ $periodoActualTitulo }}</strong></p>
<p><a href="{{ route('dashboard', ['mes' => $mes, 'anio' => $anio, 'periodo' => $periodo]) }}">Volver al dashboard</a></p>

<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Estado</th>
            <th>Asesor</th>
            <th>Monto solicitado</th>
            <th>Monto aprobado</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($registros as $base)
            <tr>
                <td>{{ $base->lote }}</td>
                <td>{{ $base->cliente }}</td>
                <td>{{ $base->cedula ?? 'N/A' }}</td>
                <td>{{ $base->estado ?? 'N/A' }}</td>
                <td>{{ $base->asesor ?? 'N/A' }}</td>
                <td>{{ is_null($base->monto_solicitado) ? 'N/A' : '$' . number_format((float)$base->monto_solicitado, 0, ',', '.') }}</td>
                <td>{{ is_null($base->monto_aprobado) ? 'N/A' : '$' . number_format((float)$base->monto_aprobado, 0, ',', '.') }}</td>
                <td>
                    @if($base->origen_registro === 'cliente_potencial')
                        <a href="{{ route('clientes-potenciales.show', $base->registro_id) }}">Ver</a>
                    @else
                        <a href="{{ route('base-asignada.show', $base->registro_id) }}">Ver</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">Sin registros para este filtro.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $registros->links() }}
@endsection
