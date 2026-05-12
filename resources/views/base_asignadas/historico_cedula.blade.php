@extends('layout')

@section('content')
<h2>Historico por cedula</h2>

<form method="get" action="{{ route('base-asignada.historico-cedula') }}">
    <label>Cedula, nombre o celular</label>
    <input type="text" name="q" value="{{ $criterio }}" placeholder="Ej: 123456789, Maria Lopez, 3150000000">
    <button type="submit">Buscar</button>
    <a href="{{ route('base-asignada.historico-cedula') }}">Limpiar</a>
</form>

@if($criterio === '')
    <p>Ingresa una cedula, nombre o celular para consultar el historico.</p>
@else
    <h3>Registros encontrados</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha carga</th>
                <th>Ultima modificacion</th>
                <th>Lote</th>
                <th>Nombre</th>
                <th>Cedula</th>
                <th>Estado actual</th>
                <th>Comercial</th>
                <th>Ver</th>
            </tr>
        </thead>
        <tbody>
            @forelse($registros ?? [] as $registro)
                <tr>
                    <td>{{ $registro->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $registro->ultima_gestion_at ? $registro->ultima_gestion_at->format('Y-m-d H:i') : 'N/A' }}</td>
                    <td>{{ $registro->lote_nombre ?? 'SIN LOTE' }}</td>
                    <td>{{ $registro->nombre }}</td>
                    <td>{{ $registro->cedula ?? 'N/A' }}</td>
                    <td>{{ $registro->estado?->nombre ?? 'Sin estado' }}</td>
                    <td>{{ $registro->asesor?->name ?? 'Sin asignar' }}</td>
                    <td><a href="{{ route('base-asignada.show', $registro->id) }}">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="8">No hay registros para ese criterio de busqueda.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($registros)
        {{ $registros->links() }}
    @endif

    <h3>Historial de gestiones</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Canal</th>
                <th>Detalle</th>
                <th>Lote</th>
                <th>Comercial</th>
                <th>Registro</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gestiones ?? [] as $gestion)
                <tr>
                    <td>{{ $gestion->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $gestion->estado?->nombre ?? 'Sin estado' }}</td>
                    <td>{{ $gestion->tipo ?? 'N/A' }}</td>
                    <td>{{ $gestion->detalle }}</td>
                    <td>{{ $gestion->baseAsignada?->lote_nombre ?? 'SIN LOTE' }}</td>
                    <td>{{ $gestion->asesor?->name ?? 'N/A' }}</td>
                    <td><a href="{{ route('base-asignada.show', $gestion->base_asignada_id) }}">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No hay gestiones para ese criterio de busqueda.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($gestiones)
        {{ $gestiones->links() }}
    @endif
@endif
@endsection
