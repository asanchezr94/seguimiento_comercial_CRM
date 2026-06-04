<?php

namespace App\Http\Controllers;

use App\Models\ClientePotencial;
use App\Models\Estado;
use App\Models\Gestion;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClientePotencialController extends Controller
{
    private const LINEAS_CREDITO = [
        'LIBRE INVERSION',
        'CREDIAPORTES',
        'CREDITO EDUCATIVO',
        'CREDITO ROTATIVO',
        'CREDITO PRIMA',
        'ADELANTO DE SALARIO',
    ];

    private const ORIGENES = [
        'llamada',
        'visita',
        'oficina',
        'redes sociales',
        'base interna',
        'referidos',
        'retomado',
    ];
    private const ESTADOS_DESEMBOLSO = [
        'Por desembolsar',
        'desembolsado',
        'aplazado',
        'negados',
        'desistido',
        'pendiente de radicar',
    ];

    private function isSupervisor(): bool
    {
        return auth()->user()?->role === 'supervisor';
    }

    private function authorizeCliente(ClientePotencial $cliente): void
    {
        if ($this->isSupervisor()) {
            return;
        }

        abort_unless($cliente->asesor_id === auth()->id(), 403);
    }

    private function forbidIfNotSupervisor(): void
    {
        abort_unless($this->isSupervisor(), 403);
    }

    private function estadoNuevoId(): int
    {
        $estadoId = Estado::where('slug', 'nuevo')->value('id');
        abort_unless($estadoId, 500, 'No existe el estado Nuevo en la base de datos.');

        return (int) $estadoId;
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
        $query = ClientePotencial::with(['estado', 'asesor'])->latest();
        if (!$this->isSupervisor()) {
            $query->where('asesor_id', auth()->id());
        }
        if (request()->filled('estado_id')) {
            $query->where('estado_id', (int) request('estado_id'));
        }
        if (request()->filled('q')) {
            $q = trim((string) request('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%");
            });
        }
        $clientes = $query->paginate(10)->withQueryString();
        $estados = $this->estadosGestionables();
        $origenes = self::ORIGENES;
        $lineasCredito = self::LINEAS_CREDITO;
        $desembolsoEstados = self::ESTADOS_DESEMBOLSO;
        return view('clientes_potenciales.index', compact('clientes', 'estados', 'origenes', 'lineasCredito'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $estados = $this->estadosGestionables();
        $origenes = self::ORIGENES;
        $lineasCredito = self::LINEAS_CREDITO;
        return view('clientes_potenciales.create', compact('estados', 'origenes', 'lineasCredito'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'fuente' => ['required', 'in:' . implode(',', self::ORIGENES)],
            'observaciones' => ['required', 'string'],
        ]);
        $data['lote_nombre'] = 'CLIENTE POTENCIAL';
        $data['estado_id'] = $this->estadoNuevoId();

        if (!$this->isSupervisor()) {
            $data['asesor_id'] = auth()->id();
        } elseif (empty($data['asesor_id'])) {
            $data['asesor_id'] = auth()->id();
        }

        ClientePotencial::create($data);
        return redirect()->route('clientes-potenciales.index')->with('ok', 'Registro creado.');
    }

    public function importar(Request $request)
    {
        $data = $request->validate([
            'archivo_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'lote_nombre' => ['required', 'string', 'max:255'],
            'fuente' => ['required', 'in:' . implode(',', self::ORIGENES)],
            'observaciones' => ['required', 'string'],
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
        if (!$header || count($header) < 2) {
            fclose($file);
            return back()->withErrors(['archivo_csv' => 'Encabezado invalido.']);
        }

        $header = array_map(function ($h) {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            return Str::lower(trim($clean));
        }, $header);

        $permitidas = ['nombre', 'telefono', 'cedula', 'linea_credito', 'email', 'empresa', 'fuente', 'observaciones', 'asesor_email'];
        $requeridas = ['nombre', 'telefono'];
        $headerMap = array_flip($header);
        foreach ($requeridas as $req) {
            if (!array_key_exists($req, $headerMap)) {
                fclose($file);
                return back()->withErrors(['archivo_csv' => "Falta la columna obligatoria: {$req}."]);
            }
        }
        foreach ($header as $columna) {
            if (!in_array($columna, $permitidas, true)) {
                fclose($file);
                return back()->withErrors(['archivo_csv' => "Columna no permitida en CSV: {$columna}."]);
            }
        }

        $creados = 0;
        $omitidos = 0;
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            $row = array_map('trim', $row);
            $get = function (string $key) use ($headerMap, $row): string {
                $idx = $headerMap[$key] ?? null;
                if ($idx === null) {
                    return '';
                }
                return trim((string) ($row[$idx] ?? ''));
            };

            $nombre = $get('nombre');
            $telefono = $get('telefono');
            $cedula = $get('cedula');
            $lineaCredito = Str::upper($get('linea_credito'));
            $email = $get('email');
            $empresa = $get('empresa');
            $fuente = Str::lower($get('fuente')) ?: $data['fuente'];
            $observaciones = $get('observaciones') ?: $data['observaciones'];
            $asesorEmail = $get('asesor_email');

            if ($nombre === '' || $telefono === '') {
                $omitidos++;
                continue;
            }

            if ($lineaCredito !== '' && !in_array($lineaCredito, self::LINEAS_CREDITO, true)) {
                $omitidos++;
                continue;
            }

            if ($fuente !== '' && !in_array($fuente, self::ORIGENES, true)) {
                $omitidos++;
                continue;
            }

            $estadoId = $this->estadoNuevoId();

            $asesorId = auth()->id();
            if ($this->isSupervisor() && $asesorEmail !== '') {
                $asesor = User::where('email', $asesorEmail)->whereIn('role', ['comercial', 'supervisor'])->first();
                if (!$asesor) {
                    $omitidos++;
                    continue;
                }
                $asesorId = $asesor->id;
            }

            ClientePotencial::create([
                'asesor_id' => $asesorId,
                'lote_nombre' => trim((string) $data['lote_nombre']),
                'estado_id' => $estadoId,
                'nombre' => $nombre,
                'cedula' => $cedula ?: null,
                'linea_credito' => $lineaCredito ?: null,
                'telefono' => $telefono ?: null,
                'email' => $email ?: null,
                'empresa' => $empresa ?: null,
                'fuente' => $fuente ?: null,
                'observaciones' => $observaciones ?: null,
            ]);

            $creados++;
        }

        fclose($file);

        return redirect()->route('clientes-potenciales.index')
            ->with('ok', "Carga completada. Creados: {$creados}. Omitidos: {$omitidos}.");
    }

    public function plantillaCsv()
    {
        $headers = ['nombre', 'telefono', 'cedula', 'linea_credito', 'email', 'empresa', 'fuente', 'observaciones', 'asesor_email'];
        $example = ['Juan Perez', '3001234567', '12345678', 'LIBRE INVERSION', 'juan@email.com', 'Empresa SAS', '', 'Cliente referido', 'comercial@empresa.com'];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'plantilla_clientes_potenciales.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cliente = ClientePotencial::with(['estado', 'gestiones.estado', 'gestiones.asesor'])->findOrFail($id);
        $this->authorizeCliente($cliente);
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        $origenes = self::ORIGENES;
        $lineasCredito = self::LINEAS_CREDITO;
        $desembolsoEstados = self::ESTADOS_DESEMBOLSO;
        $historicoAsociados = collect();
        $criterio = null;
        $campo = null;
        if (!empty($cliente->cedula)) {
            $criterio = trim((string) $cliente->cedula);
            $campo = 'cedula';
        } elseif (!empty($cliente->telefono)) {
            $criterio = trim((string) $cliente->telefono);
            $campo = 'telefono';
        } elseif (!empty($cliente->nombre)) {
            $criterio = trim((string) $cliente->nombre);
            $campo = 'nombre';
        }

        if ($criterio !== null && $criterio !== '') {
            $baseQ = DB::table('base_asignadas')
                ->leftJoin('estados', 'estados.id', '=', 'base_asignadas.estado_id')
                ->leftJoin('users', 'users.id', '=', 'base_asignadas.asesor_id')
                ->selectRaw("'base' as tipo_registro")
                ->selectRaw('base_asignadas.id as registro_id')
                ->selectRaw('base_asignadas.created_at as created_at')
                ->selectRaw('base_asignadas.ultima_gestion_at as ultima_gestion_at')
                ->selectRaw('base_asignadas.lote_nombre as lote')
                ->selectRaw('base_asignadas.nombre as nombre')
                ->selectRaw('base_asignadas.cedula as cedula')
                ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
                ->selectRaw("COALESCE(users.name, 'Sin asignar') as asesor_nombre");
            $cpQ = DB::table('cliente_potencials')
                ->leftJoin('estados', 'estados.id', '=', 'cliente_potencials.estado_id')
                ->leftJoin('users', 'users.id', '=', 'cliente_potencials.asesor_id')
                ->where('cliente_potencials.id', '!=', $cliente->id)
                ->selectRaw("'cliente_potencial' as tipo_registro")
                ->selectRaw('cliente_potencials.id as registro_id')
                ->selectRaw('cliente_potencials.created_at as created_at')
                ->selectRaw('COALESCE(cliente_potencials.ultima_gestion_at, cliente_potencials.updated_at) as ultima_gestion_at')
                ->selectRaw("COALESCE(cliente_potencials.lote_nombre, 'CLIENTE POTENCIAL') as lote")
                ->selectRaw('cliente_potencials.nombre as nombre')
                ->selectRaw('cliente_potencials.cedula as cedula')
                ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
                ->selectRaw("COALESCE(users.name, 'Sin asignar') as asesor_nombre");

            if ($campo === 'cedula') {
                $baseQ->where('base_asignadas.cedula', $criterio);
                $cpQ->where('cliente_potencials.cedula', $criterio);
            } elseif ($campo === 'telefono') {
                $baseQ->where('base_asignadas.telefono', $criterio);
                $cpQ->where('cliente_potencials.telefono', $criterio);
            } else {
                $baseQ->where('base_asignadas.nombre', $criterio);
                $cpQ->where('cliente_potencials.nombre', $criterio);
            }

            $historicoAsociados = $baseQ->unionAll($cpQ)->get()->sortByDesc('created_at')->take(20)->values();
        }

        $solicitudCierre = Gestion::where('cliente_potencial_id', $cliente->id)
            ->where('estado_id', Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id'))
            ->latest('created_at')
            ->first();
        $vinculacionCierre = $solicitudCierre?->es_vinculacion
            ? 'SI'
            : 'N/A';
        $productoCierre = 'N/A';
        if ($solicitudCierre?->es_vinculacion) {
            $productoCierre = $solicitudCierre->es_ahorro
                ? 'Ahorro'
                : 'Credito: ' . ($solicitudCierre->linea_credito_gestion ?: 'Sin linea');
        }

        return view('clientes_potenciales.show', compact('cliente', 'estados', 'origenes', 'lineasCredito', 'desembolsoEstados', 'historicoAsociados', 'vinculacionCierre', 'productoCierre'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $cliente = ClientePotencial::findOrFail($id);
        $this->authorizeCliente($cliente);
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        $origenes = self::ORIGENES;
        $lineasCredito = self::LINEAS_CREDITO;
        return view('clientes_potenciales.edit', compact('cliente', 'estados', 'origenes', 'lineasCredito'));
    }

    public function actualizarDatosBasicos(Request $request, string $id)
    {
        $cliente = ClientePotencial::findOrFail($id);
        $this->authorizeCliente($cliente);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'empresa' => ['nullable', 'string', 'max:255'],
        ]);

        $cliente->update($data);

        return redirect()->route('clientes-potenciales.show', $cliente->id)->with('ok', 'Datos basicos actualizados.');
    }

    public function retomarRegistro(string $id)
    {
        $cliente = ClientePotencial::with('estado')->findOrFail($id);
        $this->authorizeCliente($cliente);
        if ($cliente->estado?->slug !== 'cerrado') {
            return back()->withErrors(['estado_id' => 'Solo se pueden retomar registros cerrados.']);
        }

        $nuevo = ClientePotencial::create([
            'asesor_id' => auth()->id(),
            'lote_nombre' => 'RETOMADO',
            'estado_id' => $this->estadoNuevoId(),
            'nombre' => $cliente->nombre,
            'cedula' => $cliente->cedula,
            'telefono' => $cliente->telefono,
            'email' => $cliente->email,
            'empresa' => $cliente->empresa,
            'linea_credito' => $cliente->linea_credito,
            'fuente' => 'retomado',
            'observaciones' => "Registro retomado desde Cliente potencial #{$cliente->id}.",
        ]);

        return redirect()->route('clientes-potenciales.show', $nuevo->id)->with('ok', 'Registro retomado y creado como nuevo.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $cliente = ClientePotencial::findOrFail($id);
        $this->authorizeCliente($cliente);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'fuente' => ['nullable', 'in:' . implode(',', self::ORIGENES)],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if (!$this->isSupervisor()) {
            unset($data['asesor_id']);
        }

        $cliente->update($data);
        return redirect()->route('clientes-potenciales.index')->with('ok', 'Registro actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $cliente = ClientePotencial::findOrFail($id);
        $this->authorizeCliente($cliente);
        $cliente->delete();
        return redirect()->route('clientes-potenciales.index')->with('ok', 'Registro eliminado.');
    }

    public function aprobarPendiente(string $id)
    {
        $this->forbidIfNotSupervisor();
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $cliente = ClientePotencial::findOrFail($id);
        if ($cliente->estado_id !== $pendienteId) {
            return back()->withErrors(['estado_id' => 'Solo se pueden aprobar registros en estado Pendiente de aprobacion (supervisor).']);
        }
        $cols = Schema::getColumnListing('cliente_potencials');
        $hasCol = fn (string $col) => in_array($col, $cols, true);
        $update = [
            'estado_id' => $cerradoId,
        ];
        if ($hasCol('motivo_devolucion')) {
            $update['motivo_devolucion'] = null;
        }
        if ($hasCol('cierre_aprobado_at')) {
            $update['cierre_aprobado_at'] = now();
        }
        if ($hasCol('desembolso_aprobado_at')) {
            $update['desembolso_aprobado_at'] = now();
        }
        if ($hasCol('ultima_gestion_at')) {
            $update['ultima_gestion_at'] = now();
        }
        $cliente->update($update);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $cerradoId,
            'cliente_potencial_id' => $cliente->id,
            'tipo' => 'aprobacion_supervisor',
            'detalle' => 'Aprobacion de supervisor: gestion cerrada.',
        ]);

        return redirect()->route('base-asignada.pendientes')->with('ok', 'Cierre de cliente potencial aprobado.');
    }

    public function devolverPendiente(Request $request, string $id)
    {
        $this->forbidIfNotSupervisor();
        $request->validate([
            'motivo_devolucion' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $cliente = ClientePotencial::findOrFail($id);
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        if ($cliente->estado_id !== $pendienteId) {
            return back()->withErrors(['estado_id' => 'Solo se pueden devolver registros en estado Pendiente de aprobacion (supervisor).']);
        }
        $devueltaId = Estado::where('slug', 'devuelta')->value('id');
        $motivo = $request->input('motivo_devolucion');

        $cols = Schema::getColumnListing('cliente_potencials');
        $hasCol = fn (string $col) => in_array($col, $cols, true);
        $update = [
            'estado_id' => $devueltaId,
        ];
        if ($hasCol('efectivo')) {
            $update['efectivo'] = null;
        }
        if ($hasCol('monto_linea_credito')) {
            $update['monto_linea_credito'] = null;
        }
        foreach ([
            'desembolso_estado',
            'desembolso_estado_pendiente',
            'desembolso_solicitado_at',
            'desembolso_solicitado_por',
            'desembolso_aprobado_at',
            'desembolso_motivo_devolucion',
        ] as $desembolsoCol) {
            if ($hasCol($desembolsoCol)) {
                $update[$desembolsoCol] = null;
            }
        }
        if ($hasCol('cierre_solicitado_at')) {
            $update['cierre_solicitado_at'] = null;
        }
        if ($hasCol('cierre_solicitado_por')) {
            $update['cierre_solicitado_por'] = null;
        }
        if ($hasCol('cierre_aprobado_at')) {
            $update['cierre_aprobado_at'] = null;
        }
        if ($hasCol('motivo_devolucion')) {
            $update['motivo_devolucion'] = $motivo;
        }
        if ($hasCol('ultima_gestion_at')) {
            $update['ultima_gestion_at'] = now();
        }
        $cliente->update($update);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $devueltaId,
            'cliente_potencial_id' => $cliente->id,
            'tipo' => 'devolucion_supervisor',
            'detalle' => "Devolucion de supervisor: {$motivo}",
        ]);

        return redirect()->route('base-asignada.pendientes')->with('ok', 'Cierre de cliente potencial devuelto.');
    }

    public function solicitarDesembolso(Request $request, string $id)
    {
        $cliente = ClientePotencial::with('estado')->findOrFail($id);
        $this->authorizeCliente($cliente);
        if ($cliente->estado?->slug !== 'cerrado') {
            return back()->withErrors(['desembolso_estado' => 'Solo se puede cambiar el desembolso cuando el registro esta cerrado.']);
        }

        $data = $request->validate([
            'desembolso_estado' => ['required', 'in:' . implode(',', self::ESTADOS_DESEMBOLSO)],
            'detalle' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->isSupervisor()) {
            $cliente->update([
                'desembolso_estado' => $data['desembolso_estado'],
                'desembolso_estado_pendiente' => null,
                'desembolso_solicitado_at' => null,
                'desembolso_solicitado_por' => null,
                'desembolso_aprobado_at' => now(),
                'desembolso_motivo_devolucion' => null,
                'ultima_gestion_at' => now(),
            ]);

            Gestion::create([
                'asesor_id' => auth()->id(),
                'estado_id' => $cliente->estado_id,
                'cliente_potencial_id' => $cliente->id,
                'tipo' => 'desembolso_supervisor',
                'detalle' => trim('Supervisor actualizo estado desembolso a: ' . $data['desembolso_estado'] . '. ' . ($data['detalle'] ?? '')),
            ]);

            return back()->with('ok', 'Estado de desembolso actualizado.');
        }

        $cliente->update([
            'desembolso_estado_pendiente' => $data['desembolso_estado'],
            'desembolso_solicitado_at' => now(),
            'desembolso_solicitado_por' => auth()->id(),
            'desembolso_motivo_devolucion' => null,
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $cliente->estado_id,
            'cliente_potencial_id' => $cliente->id,
            'tipo' => 'solicitud_desembolso',
            'detalle' => trim('Solicitud cambio desembolso a: ' . $data['desembolso_estado'] . '. ' . ($data['detalle'] ?? '')),
        ]);

        foreach (User::where('role', 'supervisor')->pluck('id') as $supervisorId) {
            AppNotification::create([
                'user_id' => $supervisorId,
                'title' => 'Solicitud de desembolso pendiente',
                'message' => "Se solicito cambio de desembolso para cliente potencial {$cliente->nombre} por " . auth()->user()?->name . '.',
                'type' => 'desembolso_pendiente_cliente',
                'related_id' => $cliente->id,
                'related_type' => ClientePotencial::class,
                'event_at' => now(),
            ]);
        }

        return back()->with('ok', 'Cambio de desembolso enviado a aprobacion del supervisor.');
    }

    public function aprobarDesembolso(string $id)
    {
        $this->forbidIfNotSupervisor();
        $cliente = ClientePotencial::findOrFail($id);
        if (!$cliente->desembolso_estado_pendiente) {
            return back()->withErrors(['desembolso_estado' => 'No hay solicitud de desembolso pendiente.']);
        }

        $nuevoEstado = $cliente->desembolso_estado_pendiente;
        $cliente->update([
            'desembolso_estado' => $nuevoEstado,
            'desembolso_estado_pendiente' => null,
            'desembolso_solicitado_at' => null,
            'desembolso_solicitado_por' => null,
            'desembolso_aprobado_at' => now(),
            'desembolso_motivo_devolucion' => null,
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $cliente->estado_id,
            'cliente_potencial_id' => $cliente->id,
            'tipo' => 'aprobacion_desembolso',
            'detalle' => "Supervisor aprobo cambio de desembolso a: {$nuevoEstado}.",
        ]);

        return back()->with('ok', 'Cambio de desembolso aprobado.');
    }

    public function devolverDesembolso(Request $request, string $id)
    {
        $this->forbidIfNotSupervisor();
        $data = $request->validate([
            'motivo_devolucion' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $cliente = ClientePotencial::findOrFail($id);
        if (!$cliente->desembolso_estado_pendiente) {
            return back()->withErrors(['desembolso_estado' => 'No hay solicitud de desembolso pendiente.']);
        }
        $pendiente = $cliente->desembolso_estado_pendiente;
        $cliente->update([
            'desembolso_estado_pendiente' => null,
            'desembolso_solicitado_at' => null,
            'desembolso_solicitado_por' => null,
            'desembolso_motivo_devolucion' => $data['motivo_devolucion'],
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $cliente->estado_id,
            'cliente_potencial_id' => $cliente->id,
            'tipo' => 'devolucion_desembolso',
            'detalle' => "Supervisor devolvio cambio de desembolso ({$pendiente}). Motivo: {$data['motivo_devolucion']}",
        ]);

        return back()->with('ok', 'Cambio de desembolso devuelto.');
    }
}
