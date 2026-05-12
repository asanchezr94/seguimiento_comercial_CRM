<label>Nombre de base/lote</label>
<input name="lote_nombre" value="{{ old('lote_nombre', $base->lote_nombre ?? '') }}" placeholder="Ej: ESE Salud Pereira">

<label>Nombre</label>
<input name="nombre" value="{{ old('nombre', $base->nombre ?? '') }}" required>

<label>Cedula</label>
<input name="cedula" value="{{ old('cedula', $base->cedula ?? '') }}">

<label>Linea de credito</label>
<select name="linea_credito">
    <option value="">Seleccione</option>
    @foreach($lineasCredito as $linea)
        <option value="{{ $linea }}" @selected(old('linea_credito', $base->linea_credito ?? null) === $linea)>{{ $linea }}</option>
    @endforeach
</select>

<label>Telefono</label>
<input name="telefono" value="{{ old('telefono', $base->telefono ?? '') }}">

<label>Email</label>
<input name="email" value="{{ old('email', $base->email ?? '') }}">

<label>Empresa</label>
<input name="empresa" value="{{ old('empresa', $base->empresa ?? '') }}">

<label>Origen</label>
@if(!empty($base))
    <select disabled>
        <option value="">Seleccione</option>
        @foreach(($origenesBase ?? []) as $origen)
            <option value="{{ $origen }}" @selected(old('origen', $base->origen ?? null) === $origen)>{{ ucfirst($origen) }}</option>
        @endforeach
    </select>
    <small>El origen se define al cargar la base y no se puede cambiar.</small>
@else
    <select name="origen">
        <option value="">Seleccione</option>
        @foreach(($origenesBase ?? []) as $origen)
            <option value="{{ $origen }}" @selected(old('origen', $base->origen ?? null) === $origen)>{{ ucfirst($origen) }}</option>
        @endforeach
    </select>
@endif

<label>Supervisor</label>
<select name="supervisor_id" required>
    <option value="">Seleccione</option>
    @foreach($supervisores as $supervisor)
        <option value="{{ $supervisor->id }}" @selected(old('supervisor_id', $base->supervisor_id ?? null) == $supervisor->id)>{{ $supervisor->name }}</option>
    @endforeach
</select>

<label>Comercial asignado</label>
<select name="asesor_id" required>
    <option value="">Seleccione</option>
    @foreach($comerciales as $comercial)
        <option value="{{ $comercial->id }}" @selected(old('asesor_id', $base->asesor_id ?? null) == $comercial->id)>{{ $comercial->name }}</option>
    @endforeach
</select>

<label>Estado</label>
<select name="estado_id">
    <option value="">Sin estado</option>
    @foreach($estados as $estado)
        <option value="{{ $estado->id }}" @selected(old('estado_id', $base->estado_id ?? null) == $estado->id)>{{ $estado->nombre }}</option>
    @endforeach
</select>

<label>Observaciones</label>
<textarea name="observaciones">{{ old('observaciones', $base->observaciones ?? '') }}</textarea>
