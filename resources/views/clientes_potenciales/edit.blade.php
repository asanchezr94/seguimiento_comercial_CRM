@extends('layout')

@section('content')
<h2>Editar cliente potencial</h2>
<form method="post" action="{{ route('clientes-potenciales.update', $cliente) }}">
    @csrf @method('PUT')
    @include('clientes_potenciales.form', ['cliente' => $cliente])
    <button type="submit">Actualizar</button>
</form>
@endsection
