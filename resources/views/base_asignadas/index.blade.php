@extends('layout')

@section('content')
@php($isSupervisor = auth()->user()?->role === 'supervisor')
@if($isSupervisor)
<p><a href="{{ route('base-asignada.create') }}">+ Nueva base asignada</a></p>
<h3>Carga masiva CSV</h3>
<p>Encabezado con linea de credito: <code>lote_nombre,nombre,cedula,linea_credito,telefono,email,empresa,origen,observaciones,estado_slug,comercial_email</code></p>
<p>Encabezado sin linea de credito: <code>lote_nombre,nombre,cedula,telefono,email,empresa,origen,observaciones,estado_slug,comercial_email</code></p>
<p><strong>Nota:</strong> <code>comercial_email</code> ahora es opcional; si lo dejas vacio, entra sin asignar para repartir luego por lote.</p>
<form method="post" action="{{ route('base-asignada.importar') }}" enctype="multipart/form-data">
    @csrf
    <input type="file" name="archivo_csv" accept=".csv,.txt" required>
    <button type="submit">Importar</button>
</form>
@endif

<h3>Lotes</h3>
<form method="get" action="{{ route('base-asignada.index') }}">
    <label>Buscar lote</label>
    <input type="text" name="lote" value="{{ request('lote') }}" placeholder="Nombre del lote">
    <button type="submit">Filtrar</button>
    <a href="{{ route('base-asignada.index') }}">Limpiar</a>
</form>
<table>
    <thead>
        <tr>
            <th>Lote</th>
            <th>Total registros</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lotes as $lote)
        <tr>
            <td>{{ $lote->lote_nombre }}</td>
            <td>{{ $lote->total }}</td>
            <td class="actions">
                <a href="{{ route('base-asignada.lote', ['loteNombre' => $lote->lote_nombre]) }}">Ver lote</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="3">Sin lotes.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
