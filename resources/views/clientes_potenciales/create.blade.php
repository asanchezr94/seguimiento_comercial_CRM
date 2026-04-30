@extends('layout')

@section('content')
<h2>Nuevo cliente potencial</h2>
<form method="post" action="{{ route('clientes-potenciales.store') }}">
    @csrf
    @include('clientes_potenciales.form', ['cliente' => null])
    <button type="submit">Guardar</button>
</form>
@endsection
