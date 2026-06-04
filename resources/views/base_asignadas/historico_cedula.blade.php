@extends('layout')

@section('content')
<h2>Historico asociados</h2>

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
                    <td>{{ \Illuminate\Support\Carbon::parse($registro->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $registro->ultima_gestion_at ? \Illuminate\Support\Carbon::parse($registro->ultima_gestion_at)->format('Y-m-d H:i') : 'N/A' }}</td>
                    <td>{{ $registro->lote ?? 'SIN LOTE' }}</td>
                    <td>{{ $registro->nombre }}</td>
                    <td>{{ $registro->cedula ?? 'N/A' }}</td>
                    <td>{{ $registro->estado_nombre ?? 'Sin estado' }}</td>
                    <td>{{ $registro->asesor_nombre ?? 'Sin asignar' }}</td>
                    <td>
                        @if(($registro->tipo_registro ?? 'base') === 'cliente_potencial')
                            <a href="{{ route('clientes-potenciales.show', $registro->registro_id) }}">Ver</a>
                        @else
                            <a href="{{ route('base-asignada.show', $registro->registro_id) }}">Ver</a>
                        @endif
                    </td>
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
                    <td>{{ \Illuminate\Support\Carbon::parse($gestion->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $gestion->estado_nombre ?? 'Sin estado' }}</td>
                    <td>{{ $gestion->canal ?? 'N/A' }}</td>
                    <td>{{ $gestion->detalle }}</td>
                    <td>{{ $gestion->lote ?? 'SIN LOTE' }}</td>
                    <td>{{ $gestion->asesor_nombre ?? 'N/A' }}</td>
                    <td>
                        @if(($gestion->tipo_registro ?? 'base') === 'cliente_potencial')
                            <a href="{{ route('clientes-potenciales.show', $gestion->registro_id) }}">Ver</a>
                        @else
                            <a href="{{ route('base-asignada.show', $gestion->registro_id) }}">Ver</a>
                        @endif
                    </td>
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
