@extends('layout')

@section('content')
<h2>Nueva base asignada</h2>
<form method="post" action="{{ route('base-asignada.store') }}">
    @csrf
    @include('base_asignadas.form', ['base' => null])
    <button type="submit">Guardar</button>
</form>
@endsection
