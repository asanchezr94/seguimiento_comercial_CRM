@extends('layout')

@section('content')
<h2>Lotes asignados a {{ $comercial->name }}</h2>

<form method="get" action="{{ route('supervisor.comerciales.gestion', $comercial->id) }}">
    <label>Buscar lote</label>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre del lote">
    <button type="submit">Filtrar</button>
    <a href="{{ route('supervisor.comerciales.gestion', $comercial->id) }}">Limpiar</a>
</form>

<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Fecha carga</th>
            <th>Total registros</th>
            <th>Gestionados</th>
            <th>% gestion</th>
            <th>Ultima gestion</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lotes as $lote)
            @php($porc = (int)$lote->total_registros > 0 ? round(((int)$lote->gestionados / (int)$lote->total_registros) * 100, 1) : 0)
            <tr>
                <td>{{ $lote->lote_nombre }}</td>
                <td>{{ $lote->fecha_carga ? \Illuminate\Support\Carbon::parse($lote->fecha_carga)->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>{{ number_format((int)$lote->total_registros, 0, ',', '.') }}</td>
                <td>{{ number_format((int)$lote->gestionados, 0, ',', '.') }}</td>
                <td>{{ number_format((float)$porc, 1) }}%</td>
                <td>{{ $lote->ultima_modificacion ? \Illuminate\Support\Carbon::parse($lote->ultima_modificacion)->format('d/m/Y H:i') : 'N/A' }}</td>
                <td><a href="{{ route('supervisor.comerciales.gestion.lote', ['comercialId' => $comercial->id, 'loteRef' => $lote->lote_uid]) }}">Ver lote</a></td>
            </tr>
        @empty
            <tr><td colspan="7">Sin lotes para este comercial.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $lotes->links() }}

<h3>Clientes potenciales asignados a {{ $comercial->name }}</h3>
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Cedula</th>
            <th>Linea de credito</th>
            <th>Empresa</th>
            <th>Estado</th>
            <th>Ultima modificacion</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($clientesPotenciales as $cp)
            <tr>
                <td>{{ $cp->nombre }}</td>
                <td>{{ $cp->cedula ?? 'N/A' }}</td>
                <td>{{ $cp->linea_credito ?? 'N/A' }}</td>
                <td>{{ $cp->empresa ?? 'N/A' }}</td>
                <td>{{ $cp->estado?->nombre ?? 'Sin estado' }}</td>
                <td>{{ ($cp->ultima_gestion_at ?? $cp->updated_at)?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                <td><a href="{{ route('clientes-potenciales.show', $cp->id) }}">Gestionar</a></td>
            </tr>
        @empty
            <tr><td colspan="7">Sin clientes potenciales para este comercial.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $clientesPotenciales->links() }}
@endsection
