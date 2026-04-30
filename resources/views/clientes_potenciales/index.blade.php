@extends('layout')

@section('content')
<p><a href="{{ route('clientes-potenciales.create') }}">+ Nuevo cliente potencial</a></p>
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Empresa</th>
            <th>Telefono</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($clientes as $cliente)
        <tr>
            <td>{{ $cliente->nombre }}</td>
            <td>{{ $cliente->empresa }}</td>
            <td>{{ $cliente->telefono }}</td>
            <td>{{ $cliente->estado?->nombre ?? 'Sin estado' }}</td>
            <td class="actions">
                <a href="{{ route('clientes-potenciales.show', $cliente) }}">Ver</a>
                <a href="{{ route('clientes-potenciales.edit', $cliente) }}">Editar</a>
                <form method="post" action="{{ route('clientes-potenciales.destroy', $cliente) }}" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
        @empty
        <tr><td colspan="5">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
