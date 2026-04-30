@extends('layout')

@section('content')
<h2>Lote: {{ $loteNombre }}</h2>
<p>Total registros: {{ $bases->count() }}</p>

@if(auth()->user()?->role === 'supervisor')
<h3>Asignacion por lote</h3>
<p>Selecciona 1 comercial para asignar todo, o varios para distribuir equitativamente (round-robin).</p>
<form method="post" action="{{ route('base-asignada.lote.asignar', ['loteNombre' => $loteNombre]) }}">
    @csrf
    <label>Comerciales</label>
    <select name="comerciales[]" multiple size="6" required>
        @foreach($comerciales as $comercial)
            <option value="{{ $comercial->id }}">{{ $comercial->name }} ({{ $comercial->email }})</option>
        @endforeach
    </select>
    <button type="submit">Asignar lote</button>
</form>
@endif

<h3>Detalle del lote</h3>
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Cedula</th>
            <th>Linea de credito</th>
            <th>Empresa</th>
            <th>Telefono</th>
            <th>Estado</th>
            <th>Comercial actual</th>
            <th>Gestion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bases as $base)
            <tr>
                <td>{{ $base->nombre }}</td>
                <td>{{ $base->cedula ?? 'N/A' }}</td>
                <td>{{ $base->linea_credito ?? 'N/A' }}</td>
                <td>{{ $base->empresa }}</td>
                <td>{{ $base->telefono }}</td>
                <td>{{ $base->estado?->nombre ?? 'Sin estado' }}</td>
                <td>{{ $base->asesor?->name ?? 'Sin asignar' }}</td>
                <td><a href="{{ route('base-asignada.show', $base) }}">Gestionar</a></td>
            </tr>
        @empty
            <tr><td colspan="8">Sin registros en este lote.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
