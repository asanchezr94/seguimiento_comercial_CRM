<?php

namespace App\Http\Controllers;

use App\Models\BaseAsignada;
use App\Models\Estado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BaseAsignadaController extends Controller
{
    private const LINEAS_CREDITO = [
        'LIBRE INVERSION',
        'CREDIAPORTES',
        'CREDITO EDUCATIVO',
        'CREDITO ROTATIVO',
        'CREDITO PRIMA',
        'ADELANTO DE SALARIO',
    ];

    private function isSupervisor(): bool
    {
        return auth()->user()?->role === 'supervisor';
    }

    private function forbidIfNotSupervisor(): void
    {
        abort_unless($this->isSupervisor(), 403);
    }

    private function estadosGestionables()
    {
        return Estado::where('activo', true)
            ->whereNotIn('slug', ['pendiente-aprobacion-supervisor', 'devuelta', 'efectiva'])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = BaseAsignada::select('lote_nombre')
            ->selectRaw('count(*) as total')
            ->whereNotNull('lote_nombre')
            ->where('lote_nombre', '!=', '');

        if (request()->filled('lote')) {
            $lote = trim((string) request('lote'));
            $query->where('lote_nombre', 'like', "%{$lote}%");
        }

        if (!$this->isSupervisor()) {
            $query->where('asesor_id', auth()->id());
        }

        $lotes = $query->groupBy('lote_nombre')->orderBy('lote_nombre')->get();
        return view('base_asignadas.index', compact('lotes'));
    }

    public function lotes()
    {
        $this->forbidIfNotSupervisor();
        $lotes = BaseAsignada::select('lote_nombre')
            ->selectRaw('count(*) as total')
            ->whereNotNull('lote_nombre')
            ->where('lote_nombre', '!=', '')
            ->groupBy('lote_nombre')
            ->orderBy('lote_nombre')
            ->get();

        return view('base_asignadas.lotes', compact('lotes'));
    }

    public function verLote(Request $request, string $loteNombre)
    {
        $query = BaseAsignada::with(['asesor', 'estado'])->where('lote_nombre', $loteNombre)->orderBy('id');
        if (!$this->isSupervisor()) {
            $query->where('asesor_id', auth()->id());
        }

        if ($request->filled('estado_id')) {
            $query->where('estado_id', $request->integer('estado_id'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%");
            });
        }

        $bases = $query->get();
        if (!$this->isSupervisor() && $bases->isEmpty()) {
            abort(403);
        }
        $comerciales = User::where('role', 'comercial')->orderBy('name')->get();
        $estadosFiltro = Estado::where('activo', true)->orderBy('nombre')->get();

        return view('base_asignadas.lote', compact('bases', 'loteNombre', 'comerciales', 'estadosFiltro'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->forbidIfNotSupervisor();
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        $supervisores = User::where('role', 'supervisor')->orderBy('name')->get();
        $comerciales = User::where('role', 'comercial')->orderBy('name')->get();
        $lineasCredito = self::LINEAS_CREDITO;
        return view('base_asignadas.create', compact('estados', 'supervisores', 'comerciales', 'lineasCredito'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->forbidIfNotSupervisor();
        $data = $request->validate([
            'lote_nombre' => ['nullable', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'origen' => ['nullable', 'string', 'max:255'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'asesor_id' => ['required', 'exists:users,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        BaseAsignada::create($data);
        return redirect()->route('base-asignada.index')->with('ok', 'Registro creado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $base = BaseAsignada::with(['estado', 'gestiones.estado', 'asesor', 'supervisor'])->findOrFail($id);
        if (!$this->isSupervisor() && $base->asesor_id !== auth()->id()) {
            abort(403);
        }
        $estados = $this->estadosGestionables();
        $lineasCredito = self::LINEAS_CREDITO;
        return view('base_asignadas.show', compact('base', 'estados', 'lineasCredito'));
    }

    public function cerradasComercial()
    {
        abort_unless(auth()->user()?->role === 'comercial', 403);
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $bases = BaseAsignada::with(['estado'])
            ->where('asesor_id', auth()->id())
            ->where('estado_id', $cerradoId)
            ->latest()
            ->get();

        return view('base_asignadas.cerradas', compact('bases'));
    }

    public function comercialesSupervisor()
    {
        $this->forbidIfNotSupervisor();
        $comerciales = User::where('role', 'comercial')
            ->withCount('basesAsignadas')
            ->orderBy('name')
            ->get();

        return view('base_asignadas.comerciales', compact('comerciales'));
    }

    public function gestionComercialSupervisor(Request $request, string $comercialId)
    {
        $this->forbidIfNotSupervisor();
        $comercial = User::where('role', 'comercial')->findOrFail($comercialId);
        $query = BaseAsignada::with(['estado'])
            ->where('asesor_id', $comercial->id)
            ->latest();

        if ($request->filled('estado_id')) {
            $query->where('estado_id', $request->integer('estado_id'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%")
                    ->orWhere('lote_nombre', 'like', "%{$q}%");
            });
        }

        $bases = $query->get();
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();

        return view('base_asignadas.gestion_comercial', compact('comercial', 'bases', 'estados'));
    }

    public function gestionesPendientes()
    {
        $this->forbidIfNotSupervisor();
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $bases = BaseAsignada::with(['asesor'])
            ->where('estado_id', $pendienteId)
            ->latest('cierre_solicitado_at')
            ->get();

        return view('base_asignadas.pendientes', compact('bases'));
    }

    public function aprobarPendiente(string $id)
    {
        $this->forbidIfNotSupervisor();
        $base = BaseAsignada::findOrFail($id);
        $base->update([
            'estado_id' => Estado::where('slug', 'cerrado')->value('id'),
            'motivo_devolucion' => null,
        ]);

        return redirect()->route('base-asignada.pendientes')->with('ok', 'Gestion aprobada y marcada como cerrada.');
    }

    public function devolverPendiente(Request $request, string $id)
    {
        $this->forbidIfNotSupervisor();
        $request->validate([
            'motivo_devolucion' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $base = BaseAsignada::findOrFail($id);
        $base->update([
            'estado_id' => Estado::where('slug', 'devuelta')->value('id'),
            'efectivo' => null,
            'monto_linea_credito' => null,
            'cierre_solicitado_at' => null,
            'cierre_solicitado_por' => null,
            'motivo_devolucion' => $request->input('motivo_devolucion'),
        ]);

        return redirect()->route('base-asignada.pendientes')->with('ok', 'Gestion devuelta al comercial.');
    }

    public function cambiarEstadoSupervisor(Request $request, string $id)
    {
        $this->forbidIfNotSupervisor();
        $data = $request->validate([
            'estado_id' => ['required', 'exists:estados,id'],
        ]);

        $base = BaseAsignada::findOrFail($id);
        $estado = Estado::findOrFail($data['estado_id']);
        if (in_array($estado->slug, ['pendiente-aprobacion-supervisor'], true)) {
            return back()->withErrors(['estado_id' => 'Ese estado no se puede asignar manualmente.']);
        }

        $base->update([
            'estado_id' => $estado->id,
            'cierre_solicitado_at' => null,
            'cierre_solicitado_por' => null,
            'motivo_devolucion' => null,
        ]);

        return redirect()->route('base-asignada.show', $base->id)->with('ok', 'Estado actualizado por supervisor.');
    }

    public function reabrirAContactado(string $id)
    {
        $this->forbidIfNotSupervisor();
        $base = BaseAsignada::findOrFail($id);
        $base->update([
            'estado_id' => Estado::where('slug', 'contactado')->value('id'),
            'cierre_solicitado_at' => null,
            'cierre_solicitado_por' => null,
            'motivo_devolucion' => null,
        ]);

        return redirect()->route('base-asignada.show', $base->id)->with('ok', 'Registro reabierto en estado Contactado.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->forbidIfNotSupervisor();
        $base = BaseAsignada::findOrFail($id);
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        $supervisores = User::where('role', 'supervisor')->orderBy('name')->get();
        $comerciales = User::where('role', 'comercial')->orderBy('name')->get();
        $lineasCredito = self::LINEAS_CREDITO;
        return view('base_asignadas.edit', compact('base', 'estados', 'supervisores', 'comerciales', 'lineasCredito'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->forbidIfNotSupervisor();
        $data = $request->validate([
            'lote_nombre' => ['nullable', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'origen' => ['nullable', 'string', 'max:255'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'asesor_id' => ['required', 'exists:users,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        BaseAsignada::findOrFail($id)->update($data);
        return redirect()->route('base-asignada.index')->with('ok', 'Registro actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->forbidIfNotSupervisor();
        BaseAsignada::findOrFail($id)->delete();
        return redirect()->route('base-asignada.index')->with('ok', 'Registro eliminado.');
    }

    public function importar(Request $request)
    {
        $this->forbidIfNotSupervisor();

        $request->validate([
            'archivo_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $filePath = $request->file('archivo_csv')->getRealPath();
        $file = fopen($filePath, 'r');
        if ($file === false) {
            return back()->withErrors(['archivo_csv' => 'No se pudo leer el archivo.']);
        }

        $firstLine = fgets($file);
        if ($firstLine === false) {
            fclose($file);
            return back()->withErrors(['archivo_csv' => 'El archivo esta vacio.']);
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($file);

        $header = fgetcsv($file, 0, $delimiter);
        if (!$header || count($header) < 10) {
            fclose($file);
            return back()->withErrors(['archivo_csv' => 'Encabezado invalido.']);
        }

        $header = array_map(function ($h) {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            return Str::lower(trim($clean));
        }, $header);
        $esperadoConLinea = ['lote_nombre', 'nombre', 'cedula', 'linea_credito', 'telefono', 'email', 'empresa', 'origen', 'observaciones', 'estado_slug', 'comercial_email'];
        $esperadoSinLinea = ['lote_nombre', 'nombre', 'cedula', 'telefono', 'email', 'empresa', 'origen', 'observaciones', 'estado_slug', 'comercial_email'];
        $usaLineaCredito = $header === $esperadoConLinea;
        $sinLineaCredito = $header === $esperadoSinLinea;
        if (!$usaLineaCredito && !$sinLineaCredito) {
            fclose($file);
            return back()->withErrors(['archivo_csv' => 'El encabezado no coincide con el formato esperado.']);
        }

        $creados = 0;
        $omitidos = 0;

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            if (count($row) < 10) {
                $omitidos++;
                continue;
            }

            $row = array_map('trim', $row);
            if ($usaLineaCredito) {
                [$loteNombre, $nombre, $cedula, $lineaCredito, $telefono, $email, $empresa, $origen, $observaciones, $estadoSlug, $comercialEmail] = $row;
            } else {
                [$loteNombre, $nombre, $cedula, $telefono, $email, $empresa, $origen, $observaciones, $estadoSlug, $comercialEmail] = $row;
                $lineaCredito = '';
            }
            if ($nombre === '') {
                $omitidos++;
                continue;
            }

            $comercial = null;
            if ($comercialEmail !== '') {
                $comercial = User::where('email', $comercialEmail)->where('role', 'comercial')->first();
            }
            if ($comercialEmail !== '' && !$comercial) {
                $omitidos++;
                continue;
            }

            $estadoId = null;
            if ($estadoSlug !== '') {
                $estado = Estado::where('slug', Str::slug(Str::lower($estadoSlug)))->first();
                $estadoId = $estado?->id;
            }

            if ($lineaCredito !== '' && !in_array($lineaCredito, self::LINEAS_CREDITO, true)) {
                $omitidos++;
                continue;
            }

            BaseAsignada::create([
                'supervisor_id' => auth()->id(),
                'lote_nombre' => $loteNombre ?: null,
                'asesor_id' => $comercial?->id,
                'estado_id' => $estadoId,
                'nombre' => $nombre,
                'cedula' => $cedula ?: null,
                'linea_credito' => $lineaCredito ?: null,
                'telefono' => $telefono ?: null,
                'email' => $email ?: null,
                'empresa' => $empresa ?: null,
                'origen' => $origen ?: null,
                'observaciones' => $observaciones ?: null,
            ]);

            $creados++;
        }

        fclose($file);

        return redirect()->route('base-asignada.index')->with('ok', "Importacion completada. Creados: {$creados}. Omitidos: {$omitidos}.");
    }

    public function asignarLote(Request $request, string $loteNombre)
    {
        $this->forbidIfNotSupervisor();

        $data = $request->validate([
            'comerciales' => ['required', 'array', 'min:1'],
            'comerciales.*' => ['required', 'exists:users,id'],
        ]);

        $comerciales = User::whereIn('id', $data['comerciales'])->where('role', 'comercial')->pluck('id')->values();
        if ($comerciales->isEmpty()) {
            return back()->withErrors(['comerciales' => 'Selecciona al menos un comercial valido.']);
        }

        $registros = BaseAsignada::where('lote_nombre', $loteNombre)->orderBy('id')->get();
        $total = $registros->count();
        if ($total === 0) {
            return back()->withErrors(['comerciales' => 'El lote no tiene registros.']);
        }

        foreach ($registros as $index => $registro) {
            $asignado = $comerciales[$index % $comerciales->count()];
            $registro->update(['asesor_id' => $asignado, 'supervisor_id' => auth()->id()]);
        }

        return redirect()->route('base-asignada.lote', ['loteNombre' => $loteNombre])
            ->with('ok', "Lote asignado. Registros repartidos: {$total}.");
    }
}
