<label>Nombre</label>
<input name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}" required>

<label>Cedula</label>
<input name="cedula" value="{{ old('cedula', $cliente->cedula ?? '') }}">

<label>Linea de credito</label>
<select name="linea_credito">
    <option value="">Seleccione</option>
    @foreach(($lineasCredito ?? []) as $linea)
        <option value="{{ $linea }}" @selected(old('linea_credito', $cliente->linea_credito ?? null) === $linea)>{{ $linea }}</option>
    @endforeach
</select>

<label>Telefono</label>
<input name="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}">

<label>Email</label>
<input name="email" value="{{ old('email', $cliente->email ?? '') }}">

<label>Empresa</label>
<input name="empresa" value="{{ old('empresa', $cliente->empresa ?? '') }}">

<label>Origen</label>
<select name="fuente" required>
    <option value="">Seleccione</option>
    @foreach(($origenes ?? []) as $origen)
        <option value="{{ $origen }}" @selected(old('fuente', $cliente->fuente ?? null) === $origen)>{{ ucfirst($origen) }}</option>
    @endforeach
</select>

<label>Observaciones</label>
<textarea name="observaciones" required>{{ old('observaciones', $cliente->observaciones ?? '') }}</textarea>
