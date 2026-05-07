@extends('layout')

@section('content')
<h2>Comerciales</h2>
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Total registros</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($comerciales as $comercial)
            <tr>
                <td>{{ $comercial->name }}</td>
                <td>{{ $comercial->bases_asignadas_count }}</td>
                <td><a href="{{ route('supervisor.comerciales.gestion', $comercial->id) }}">Ver gestion</a></td>
            </tr>
        @empty
            <tr><td colspan="3">No hay comerciales.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $comerciales->links() }}
@endsection
