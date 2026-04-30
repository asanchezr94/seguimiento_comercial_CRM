@extends('layout')

@section('content')
<h2>Gestiones pendientes por aprobar</h2>
<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Cliente</th>
            <th>Cedula</th>
            <th>Comercial</th>
            <th>Efectivo</th>
            <th>Monto</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bases as $base)
            <tr>
                <td>{{ $base->lote_nombre }}</td>
                <td>{{ $base->nombre }}</td>
                <td>{{ $base->cedula }}</td>
                <td>{{ $base->asesor?->name }}</td>
                <td>{{ is_null($base->efectivo) ? 'N/A' : ($base->efectivo ? 'SI' : 'NO') }}</td>
                <td>{{ is_null($base->monto_linea_credito) ? 'N/A' : number_format((float)$base->monto_linea_credito, 0, ',', '.') }}</td>
                <td class="actions">
                    <form method="post" action="{{ route('base-asignada.pendientes.aprobar', $base->id) }}" class="inline">
                        @csrf
                        <button type="submit">Aprobar</button>
                    </form>
                    <form method="post" action="{{ route('base-asignada.pendientes.devolver', $base->id) }}" class="inline">
                        @csrf
                        <input type="text" name="motivo_devolucion" placeholder="Motivo de devolucion" required style="width:220px;">
                        <button type="submit">Devolver</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No hay gestiones pendientes.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
