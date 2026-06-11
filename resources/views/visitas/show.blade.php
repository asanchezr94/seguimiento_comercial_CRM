@extends('layout')

@section('content')
<h2>Detalle de visita</h2>

<p>
    <a class="btn-link" href="{{ route('visitas.index', ['mes' => $visita->programada_at->month, 'anio' => $visita->programada_at->year]) }}">Volver al calendario</a>
</p>

<table data-no-global-filters>
    <tbody>
        <tr>
            <th>Cliente</th><td>{{ $visita->cliente_nombre }}</td>
            <th>Asesor</th><td>{{ $visita->asesor?->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Fecha inicio</th><td>{{ $visita->programada_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
            <th>Fecha fin</th><td>{{ $visita->finaliza_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Estado</th><td>{{ ucfirst($visita->estado) }}</td>
            <th>Registrada en</th><td>{{ $visita->registrada_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Telefono</th><td>{{ $visita->telefono ?? 'N/A' }}</td>
            <th>Direccion</th><td>{{ $visita->direccion ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Titulo / motivo</th><td colspan="3">{{ $visita->titulo ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Detalle inicial</th><td colspan="3">{{ $visita->detalle_inicial ?? 'Sin detalle inicial.' }}</td>
        </tr>
        <tr>
            <th>Resultado / observacion final</th><td colspan="3">{{ $visita->resultado ?? 'Sin resultado registrado.' }}</td>
        </tr>
    </tbody>
</table>

@if($visita->estado === 'programada' && $puedeRegistrar)
    <h3>Registrar resultado</h3>
    <form method="post" action="{{ route('visitas.registrar', $visita->id) }}">
        @csrf
        <label>Estado</label>
        <select name="estado" required>
            <option value="realizada">Realizada</option>
            <option value="cancelada">Cancelada</option>
        </select>
        <label>Resultado / observacion final</label>
        <textarea name="resultado" required placeholder="Describe que se hizo en la visita..."></textarea>
        <button type="submit">Guardar resultado</button>
    </form>
@elseif($visita->estado === 'programada')
    <p><strong>Nota:</strong> puedes ver esta visita, pero solo el asesor asignado o un supervisor puede registrar el resultado.</p>
@endif
@endsection
