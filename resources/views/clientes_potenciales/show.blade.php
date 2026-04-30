@extends('layout')

@section('content')
<h2>{{ $cliente->nombre }}</h2>
<p>Estado actual: <strong>{{ $cliente->estado?->nombre ?? 'Sin estado' }}</strong></p>
<p>Empresa: {{ $cliente->empresa }}</p>
<p>Telefono: {{ $cliente->telefono }}</p>
<p>Email: {{ $cliente->email }}</p>
<p>Observaciones: {{ $cliente->observaciones }}</p>

<h3>Nueva gestion</h3>
<form method="post" action="{{ route('gestiones.store') }}">
    @csrf
    <input type="hidden" name="cliente_potencial_id" value="{{ $cliente->id }}">
    <label>Tipo</label>
    <input name="tipo" value="{{ old('tipo', 'llamada') }}" required>
    <label>Estado resultante</label>
    <select name="estado_id">
        <option value="">No cambiar</option>
        @foreach($estados as $estado)
            <option value="{{ $estado->id }}">{{ $estado->nombre }}</option>
        @endforeach
    </select>
    <label>Detalle de gestion</label>
    <textarea name="detalle" required>{{ old('detalle') }}</textarea>
    <label>Proxima gestion</label>
    <input type="datetime-local" name="proxima_gestion_at" value="{{ old('proxima_gestion_at') }}">
    <button type="submit">Registrar gestion</button>
</form>

<h3>Historial</h3>
<table>
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Detalle</th></tr></thead>
    <tbody>
        @forelse($cliente->gestiones as $gestion)
            <tr>
                <td>{{ $gestion->created_at }}</td>
                <td>{{ $gestion->tipo }}</td>
                <td>{{ $gestion->estado?->nombre }}</td>
                <td>{{ $gestion->detalle }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Sin gestiones.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
