<label>Nombre</label>
<input name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}" required>

<label>Telefono</label>
<input name="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}">

<label>Email</label>
<input name="email" value="{{ old('email', $cliente->email ?? '') }}">

<label>Empresa</label>
<input name="empresa" value="{{ old('empresa', $cliente->empresa ?? '') }}">

<label>Fuente</label>
<input name="fuente" value="{{ old('fuente', $cliente->fuente ?? '') }}">

<label>Estado</label>
<select name="estado_id">
    <option value="">Sin estado</option>
    @foreach($estados as $estado)
        <option value="{{ $estado->id }}" @selected(old('estado_id', $cliente->estado_id ?? null) == $estado->id)>{{ $estado->nombre }}</option>
    @endforeach
</select>

<label>Observaciones</label>
<textarea name="observaciones">{{ old('observaciones', $cliente->observaciones ?? '') }}</textarea>
