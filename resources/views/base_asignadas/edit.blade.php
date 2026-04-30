@extends('layout')

@section('content')
<h2>Editar base asignada</h2>
<form method="post" action="{{ route('base-asignada.update', $base) }}">
    @csrf @method('PUT')
    @include('base_asignadas.form', ['base' => $base])
    <button type="submit">Actualizar</button>
</form>
@endsection
