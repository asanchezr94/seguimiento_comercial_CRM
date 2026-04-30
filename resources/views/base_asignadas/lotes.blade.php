@extends('layout')

@section('content')
<h2>Lotes de base</h2>
<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Total registros</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lotes as $lote)
            <tr>
                <td>{{ $lote->lote_nombre }}</td>
                <td>{{ $lote->total }}</td>
                <td><a href="{{ route('base-asignada.lote', ['loteNombre' => $lote->lote_nombre]) }}">Ver lote</a></td>
            </tr>
        @empty
            <tr><td colspan="3">No hay lotes creados.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
