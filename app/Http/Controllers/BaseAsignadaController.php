<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\BaseAsignada;
use App\Models\Estado;
use App\Models\Gestion;
use App\Models\Persona;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    private const ORIGENES_BASE = [
        'llamada',
        'visita',
        'oficina',
        'redes sociales',
        'base interna',
        'referidos',
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

    private function buildLoteUid(string $loteNombre, ?string $suffix = null): string
    {
        $base = Str::slug($loteNombre);
        if ($base === '') {
            $base = 'lote';
        }
        $suffix = $suffix ?: now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        return "{$base}-{$suffix}";
    }

    private function resolvePersonaId(?string $cedula, ?string $nombre = null, ?string $telefono = null, ?string $email = null): ?int
    {
        $ced = trim((string) $cedula);
        if ($ced === '') {
            return null;
        }
        $persona = Persona::firstOrCreate(
            ['cedula' => $ced],
            [
                'nombre' => $nombre ?: null,
                'telefono' => $telefono ?: null,
                'email' => $email ?: null,
            ]
        );

        $dirty = false;
        if (!$persona->nombre && $nombre) {
            $persona->nombre = $nombre;
            $dirty = true;
        }
        if (!$persona->telefono && $telefono) {
            $persona->telefono = $telefono;
            $dirty = true;
        }
        if (!$persona->email && $email) {
            $persona->email = $email;
            $dirty = true;
        }
        if ($dirty) {
            $persona->save();
        }
        return (int) $persona->id;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = BaseAsignada::select('lote_uid', 'lote_nombre')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(distinct case when exists (select 1 from gestions where gestions.base_asignada_id = base_asignadas.id) then base_asignadas.id end) as gestionados')
            ->selectRaw('min(created_at) as fecha_carga')
            ->selectRaw('min(origen) as origen_base')
            ->selectRaw('max(ultima_gestion_at) as ultima_modificacion')
            ->selectRaw("(select GROUP_CONCAT(distinct users.name order by users.name separator ', ')
                from base_asignadas ba2
                left join users on users.id = ba2.asesor_id
                where ba2.lote_uid = base_asignadas.lote_uid
                  and ba2.asesor_id is not null
            ) as comerciales_asignados")
            ->whereNotNull('lote_uid')
            ->where('lote_uid', '!=', '');

        if (request()->filled('lote')) {
            $lote = trim((string) request('lote'));
            $query->where('lote_nombre', 'like', "%{$lote}%");
        }

        if (!$this->isSupervisor()) {
            $query->where('asesor_id', auth()->id());
        }

        $lotes = $query->groupBy('lote_uid', 'lote_nombre')->orderBy('lote_nombre')->paginate(15)->withQueryString();
        $lotes->each(function ($lote) {
            $total = (int) $lote->total;
            $gestionados = (int) $lote->gestionados;
            $lote->porcentaje_gestion = $total > 0 ? round(($gestionados / $total) * 100, 1) : 0;
        });
        return view('base_asignadas.index', compact('lotes'));
    }

    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $esSupervisor = $this->isSupervisor();
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $devueltaId = Estado::where('slug', 'devuelta')->value('id');

        $baseQuery = BaseAsignada::query();
        if (!$esSupervisor) {
            $baseQuery->where('asesor_id', $user?->id);
        }

        $basePorLote = BaseAsignada::query();
        if (!$esSupervisor) {
            $basePorLote->where('asesor_id', $user?->id);
        }

        $mes = max(1, min(12, (int) $request->input('mes', now()->month)));
        $anio = (int) $request->input('anio', now()->year);
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) now()->year;
        }
        $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes = (clone $inicioMes)->endOfMonth();
        $inicioMesAnterior = (clone $inicioMes)->subMonthNoOverflow()->startOfMonth();
        $finMesAnterior = (clone $inicioMesAnterior)->endOfMonth();

        $totalBases = (clone $baseQuery)->count();
        $gestionadas = (clone $baseQuery)->whereHas('gestiones')->count();
        $pendientesAprobacion = $pendienteId ? (clone $baseQuery)->where('estado_id', $pendienteId)->count() : 0;
        $cerradas = $cerradoId ? (clone $baseQuery)->where('estado_id', $cerradoId)->count() : 0;
        $devueltas = $devueltaId ? (clone $baseQuery)->where('estado_id', $devueltaId)->count() : 0;
        $montoCerrado = $cerradoId ? (clone $baseQuery)->where('estado_id', $cerradoId)->sum('monto_linea_credito') : 0;

        $porcentajeGestion = $totalBases > 0 ? round(($gestionadas / $totalBases) * 100, 1) : 0;
        $porcentajeCierre = $totalBases > 0 ? round(($cerradas / $totalBases) * 100, 1) : 0;

        $metricasPeriodo = function (Carbon $inicio, Carbon $fin) use ($basePorLote, $cerradoId) {
            $registrosCargadosPeriodo = (clone $basePorLote)
                ->whereBetween('created_at', [$inicio, $fin])
                ->count();
            $baseIdsPeriodo = (clone $basePorLote)->select('id');
            $registrosGestionadosPeriodo = Gestion::query()
                ->whereIn('base_asignada_id', $baseIdsPeriodo)
                ->whereBetween('created_at', [$inicio, $fin])
                ->distinct('base_asignada_id')
                ->count('base_asignada_id');
            $cierresBasePeriodo = (clone $basePorLote)
                ->where('estado_id', $cerradoId)
                ->where(function ($q) use ($inicio, $fin, $cerradoId) {
                    $q->whereBetween('cierre_solicitado_at', [$inicio, $fin])
                        ->orWhereExists(function ($sub) use ($inicio, $fin, $cerradoId) {
                            $sub->selectRaw('1')
                                ->from('gestions')
                                ->whereColumn('gestions.base_asignada_id', 'base_asignadas.id')
                                ->where('gestions.estado_id', $cerradoId)
                                ->whereBetween('gestions.created_at', [$inicio, $fin]);
                        });
                });

            $cierresPeriodo = (clone $cierresBasePeriodo)->count();
            $montoPeriodo = (clone $cierresBasePeriodo)->sum('monto_linea_credito');

            $porcentajeGestionPeriodo = $registrosCargadosPeriodo > 0
                ? round(($registrosGestionadosPeriodo / $registrosCargadosPeriodo) * 100, 1)
                : 0;
            $porcentajeCierrePeriodo = $registrosGestionadosPeriodo > 0
                ? round(($cierresPeriodo / $registrosGestionadosPeriodo) * 100, 1)
                : 0;
            return [
                'registros_cargados' => $registrosCargadosPeriodo,
                'registros_gestionados' => $registrosGestionadosPeriodo,
                'porcentaje_gestion' => $porcentajeGestionPeriodo,
                'cierres' => $cierresPeriodo,
                'porcentaje_cierre' => $porcentajeCierrePeriodo,
                'monto' => $montoPeriodo,
            ];
        };

        $kpiMesActual = $metricasPeriodo($inicioMes, $finMes);
        $kpiMesAnterior = $metricasPeriodo($inicioMesAnterior, $finMesAnterior);
        $tiempoInvertidoMesMin = 0;
        $promTiempoCierreMesMin = 0;
        $efectivoSiMes = 0;
        $efectivoNoMes = 0;
        if (!$esSupervisor && $user) {
            $tiempoInvertidoMesMin = (int) Gestion::query()
                ->join('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
                ->where('base_asignadas.asesor_id', $user->id)
                ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
                ->sum('gestions.minutos_invertidos');

            $cierresMesUsuario = BaseAsignada::query()
                ->where('asesor_id', $user->id)
                ->where('estado_id', $cerradoId)
                ->where(function ($q) use ($inicioMes, $finMes, $cerradoId) {
                    $q->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes])
                        ->orWhereExists(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                            $sub->selectRaw('1')
                                ->from('gestions')
                                ->whereColumn('gestions.base_asignada_id', 'base_asignadas.id')
                                ->where('gestions.estado_id', $cerradoId)
                                ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                        });
                })
                ->get(['asignado_at', 'created_at', 'cierre_solicitado_at']);

            if ($cierresMesUsuario->isNotEmpty()) {
                $mins = [];
                foreach ($cierresMesUsuario as $cierreBase) {
                    $inicio = $cierreBase->asignado_at ?: $cierreBase->created_at;
                    $fin = $cierreBase->cierre_solicitado_at ?: $cierreBase->created_at;
                    if ($inicio && $fin) {
                        $mins[] = Carbon::parse($inicio)->diffInMinutes(Carbon::parse($fin));
                    }
                }
                if (count($mins) > 0) {
                    $promTiempoCierreMesMin = (int) round(array_sum($mins) / count($mins));
                }
            }
            $efectivoSiMes = (int) BaseAsignada::query()
                ->where('asesor_id', $user->id)
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes])
                ->count();
            $efectivoNoMes = (int) BaseAsignada::query()
                ->where('asesor_id', $user->id)
                ->where('estado_id', $cerradoId)
                ->where('efectivo', false)
                ->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes])
                ->count();
        }

        $canalesPermitidos = ['visita', 'oficina', 'llamada', 'redes sociales'];
        $canalesMesRaw = Gestion::query()
            ->join('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
            ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
            ->when(!$esSupervisor, function ($q) use ($user) {
                $q->where('base_asignadas.asesor_id', $user?->id);
            })
            ->selectRaw("LOWER(TRIM(gestions.tipo)) as canal")
            ->selectRaw('count(*) as total_gestiones')
            ->selectRaw('count(distinct gestions.base_asignada_id) as registros_unicos')
            ->groupByRaw("LOWER(TRIM(gestions.tipo))")
            ->orderByDesc('total_gestiones')
            ->get();
        $canalesMes = $canalesMesRaw
            ->filter(fn ($r) => in_array($r->canal, $canalesPermitidos, true))
            ->values();

        $cierresCanalMesRaw = Gestion::query()
            ->join('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
            ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
            ->where('gestions.estado_id', $pendienteId)
            ->when(!$esSupervisor, function ($q) use ($user) {
                $q->where('base_asignadas.asesor_id', $user?->id);
            })
            ->selectRaw("LOWER(TRIM(gestions.tipo)) as canal")
            ->selectRaw('count(*) as solicitudes_cierre')
            ->selectRaw('count(distinct gestions.base_asignada_id) as registros_unicos')
            ->selectRaw('sum(case when base_asignadas.estado_id = ? then 1 else 0 end) as cierres_aprobados', [$cerradoId ?? 0])
            ->selectRaw('sum(case when base_asignadas.estado_id = ? then COALESCE(base_asignadas.monto_linea_credito, 0) else 0 end) as monto_aprobado', [$cerradoId ?? 0])
            ->groupByRaw("LOWER(TRIM(gestions.tipo))")
            ->orderByDesc('solicitudes_cierre')
            ->get();
        $cierresCanalMes = $cierresCanalMesRaw
            ->filter(fn ($r) => in_array($r->canal, $canalesPermitidos, true))
            ->values();

        $estadosResumen = (clone $baseQuery)
            ->leftJoin('estados', 'estados.id', '=', 'base_asignadas.estado_id')
            ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
            ->selectRaw('count(*) as total')
            ->groupBy('estados.nombre')
            ->orderByDesc('total')
            ->get();

        $comercialesResumen = collect();
        if ($esSupervisor) {
            $comercialesResumen = User::where('role', 'comercial')
                ->withCount([
                    'basesAsignadas as total_registros' => function ($q) use ($inicioMes, $finMes) {
                        $q->whereBetween('base_asignadas.created_at', [$inicioMes, $finMes]);
                    },
                    'basesAsignadas as gestionados_registros' => function ($q) use ($inicioMes, $finMes) {
                        $q->whereHas('gestiones', function ($g) use ($inicioMes, $finMes) {
                            $g->whereBetween('created_at', [$inicioMes, $finMes]);
                        });
                    },
                    'basesAsignadas as cerrados_registros' => function ($q) use ($cerradoId, $inicioMes, $finMes) {
                        $q->where('estado_id', $cerradoId)
                            ->where(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                                $sub->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes])
                                    ->orWhereExists(function ($sq) use ($inicioMes, $finMes, $cerradoId) {
                                        $sq->selectRaw('1')
                                            ->from('gestions')
                                            ->whereColumn('gestions.base_asignada_id', 'base_asignadas.id')
                                            ->where('gestions.estado_id', $cerradoId)
                                            ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                                    });
                            });
                    },
                    'basesAsignadas as pendientes_registros' => function ($q) use ($pendienteId, $inicioMes, $finMes) {
                        $q->where('estado_id', $pendienteId)
                            ->whereBetween('base_asignadas.created_at', [$inicioMes, $finMes]);
                    },
                ])
                ->orderBy('name')
                ->get()
                ->map(function ($comercial) use ($inicioMes, $finMes, $cerradoId) {
                    $baseMesAsesor = BaseAsignada::query()
                        ->where('asesor_id', $comercial->id)
                        ->whereBetween('created_at', [$inicioMes, $finMes]);
                    $asignadosMes = (clone $baseMesAsesor)->count();

                    $cierresBaseMes = BaseAsignada::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where(function ($q) use ($inicioMes, $finMes, $cerradoId) {
                            $q->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes])
                                ->orWhereExists(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                                    $sub->selectRaw('1')
                                        ->from('gestions')
                                        ->whereColumn('gestions.base_asignada_id', 'base_asignadas.id')
                                        ->where('gestions.estado_id', $cerradoId)
                                        ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                                });
                        });
                    $cierresMes = (clone $cierresBaseMes)->count();
                    $montoColocadoMes = (clone $cierresBaseMes)->sum('monto_linea_credito');
                    $porcentajeCierreVsAsignados = $asignadosMes > 0 ? round(($cierresMes / $asignadosMes) * 100, 1) : 0;
                    $tiempoInvertidoMinMes = (int) Gestion::query()
                        ->join('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
                        ->where('base_asignadas.asesor_id', $comercial->id)
                        ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
                        ->sum('gestions.minutos_invertidos');
                    $promTiempoCierreMin = 0;
                    $cierresConTiempo = (clone $cierresBaseMes)->get(['asignado_at', 'created_at', 'cierre_solicitado_at']);
                    if ($cierresConTiempo->isNotEmpty()) {
                        $mins = [];
                        foreach ($cierresConTiempo as $cierreBase) {
                            $inicio = $cierreBase->asignado_at ?: $cierreBase->created_at;
                            $fin = $cierreBase->cierre_solicitado_at ?: $cierreBase->created_at;
                            if ($inicio && $fin) {
                                $mins[] = Carbon::parse($inicio)->diffInMinutes(Carbon::parse($fin));
                            }
                        }
                        if (count($mins) > 0) {
                            $promTiempoCierreMin = (int) round(array_sum($mins) / count($mins));
                        }
                    }

                    $comercial->asignados_mes = $asignadosMes;
                    $comercial->cierres_mes = $cierresMes;
                    $comercial->porcentaje_cierre_vs_asignados = $porcentajeCierreVsAsignados;
                    $comercial->monto_colocado_mes = $montoColocadoMes;
                    $comercial->tiempo_invertido_min_mes = $tiempoInvertidoMinMes;
                    $comercial->prom_tiempo_cierre_min = $promTiempoCierreMin;
                    $comercial->efectivo_si_mes = (int) BaseAsignada::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where('efectivo', true)
                        ->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes])
                        ->count();
                    $comercial->efectivo_no_mes = (int) BaseAsignada::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where('efectivo', false)
                        ->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes])
                        ->count();
                    return $comercial;
                });
        }

        return view('dashboard.index', compact(
            'esSupervisor',
            'totalBases',
            'gestionadas',
            'pendientesAprobacion',
            'cerradas',
            'devueltas',
            'montoCerrado',
            'porcentajeGestion',
            'porcentajeCierre',
            'estadosResumen',
            'comercialesResumen',
            
            'mes',
            'anio',
            'inicioMes',
            'kpiMesActual',
            'kpiMesAnterior',
            'canalesMes',
            'cierresCanalMes',
            'tiempoInvertidoMesMin',
            'promTiempoCierreMesMin',
            'efectivoSiMes',
            'efectivoNoMes'
        ));
    }

    public function lotes()
    {
        $this->forbidIfNotSupervisor();
        $lotes = BaseAsignada::select('lote_uid', 'lote_nombre')
            ->selectRaw('count(*) as total')
            ->whereNotNull('lote_uid')
            ->where('lote_uid', '!=', '')
            ->groupBy('lote_uid', 'lote_nombre')
            ->orderBy('lote_nombre')
            ->paginate(15)
            ->withQueryString();

        return view('base_asignadas.lotes', compact('lotes'));
    }

    public function verLote(Request $request, string $loteRef)
    {
        $baseQuery = BaseAsignada::where('lote_uid', $loteRef);
        if (!(clone $baseQuery)->exists()) {
            $baseQuery = BaseAsignada::where('lote_nombre', $loteRef);
        }
        if (!$this->isSupervisor()) {
            $baseQuery->where('asesor_id', auth()->id());
        }
        $totalRegistrosLote = (clone $baseQuery)->count();

        $query = BaseAsignada::with(['asesor', 'estado'])->whereIn('id', (clone $baseQuery)->select('id'))->orderBy('id');
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

        $bases = $query->paginate(20)->withQueryString();
        if (!$this->isSupervisor() && $bases->isEmpty()) {
            abort(403);
        }
        $loteNombre = $bases->first()?->lote_nombre ?? $loteRef;
        $loteUid = $bases->first()?->lote_uid ?? $loteRef;
        $comerciales = User::where('role', 'comercial')->orderBy('name')->get();
        $estadosFiltro = Estado::where('activo', true)->orderBy('nombre')->get();
        $totalSinGestion = (clone $baseQuery)
            ->whereDoesntHave('gestiones')
            ->count();

        return view('base_asignadas.lote', compact('bases', 'loteNombre', 'loteUid', 'comerciales', 'estadosFiltro', 'totalSinGestion', 'totalRegistrosLote'));
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
        $origenesBase = self::ORIGENES_BASE;
        return view('base_asignadas.create', compact('estados', 'supervisores', 'comerciales', 'lineasCredito', 'origenesBase'));
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
            'origen' => ['nullable', 'in:' . implode(',', self::ORIGENES_BASE)],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'asesor_id' => ['required', 'exists:users,id'],
            'observaciones' => ['nullable', 'string'],
        ]);
        $data['lote_uid'] = !empty($data['lote_nombre'])
            ? $this->buildLoteUid((string) $data['lote_nombre'])
            : null;

        $data['persona_id'] = $this->resolvePersonaId(
            $data['cedula'] ?? null,
            $data['nombre'] ?? null,
            $data['telefono'] ?? null,
            $data['email'] ?? null
        );
        $data['asignado_at'] = !empty($data['asesor_id']) ? now() : null;
        BaseAsignada::create($data);
        return redirect()->route('base-asignada.index')->with('ok', 'Registro creado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $base = BaseAsignada::with(['estado', 'asesor', 'supervisor', 'persona'])->findOrFail($id);
        if (!$this->isSupervisor() && $base->asesor_id !== auth()->id()) {
            abort(403);
        }
        $gestiones = Gestion::with(['estado', 'asesor'])
            ->where('base_asignada_id', $base->id)
            ->latest('created_at')
            ->paginate(10, ['*'], 'hist_page')
            ->withQueryString();
        $historicoCedula = collect();
        if ($base->persona_id) {
            $historicoCedula = BaseAsignada::with(['estado', 'asesor'])
                ->where('persona_id', $base->persona_id)
                ->where('id', '!=', $base->id)
                ->latest('created_at')
                ->limit(15)
                ->get();
        } elseif (!empty($base->telefono)) {
            $telefono = trim((string) $base->telefono);
            $historicoCedula = BaseAsignada::with(['estado', 'asesor'])
                ->where('id', '!=', $base->id)
                ->where('telefono', $telefono)
                ->latest('created_at')
                ->limit(15)
                ->get();
        } elseif (!empty($base->nombre)) {
            $nombre = trim((string) $base->nombre);
            $historicoCedula = BaseAsignada::with(['estado', 'asesor'])
                ->where('id', '!=', $base->id)
                ->where('nombre', $nombre)
                ->latest('created_at')
                ->limit(15)
                ->get();
        }
        $estados = $this->estadosGestionables();
        $lineasCredito = self::LINEAS_CREDITO;
        $tiempoInvertidoRegistroMin = (int) Gestion::where('base_asignada_id', $base->id)->sum('minutos_invertidos');
        return view('base_asignadas.show', compact('base', 'gestiones', 'historicoCedula', 'estados', 'lineasCredito', 'tiempoInvertidoRegistroMin'));
    }

    public function historicoCedula(Request $request)
    {
        $criterio = trim((string) $request->input('q', ''));
        $registros = null;
        $gestiones = null;

        if ($criterio !== '') {
            $queryBase = BaseAsignada::with(['estado', 'asesor'])
                ->where(function ($q) use ($criterio) {
                    $q->where('cedula', 'like', "%{$criterio}%")
                        ->orWhere('nombre', 'like', "%{$criterio}%")
                        ->orWhere('telefono', 'like', "%{$criterio}%");
                });
            if (!$this->isSupervisor()) {
                $queryBase->where('asesor_id', auth()->id());
            }

            $registros = (clone $queryBase)
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString();

            $baseIds = (clone $queryBase)->select('id');
            $gestiones = Gestion::with(['estado', 'asesor', 'baseAsignada'])
                ->whereIn('base_asignada_id', $baseIds)
                ->latest('created_at')
                ->paginate(20, ['*'], 'hist_page')
                ->withQueryString();
        }

        return view('base_asignadas.historico_cedula', compact('criterio', 'registros', 'gestiones'));
    }

    public function cerradasComercial()
    {
        abort_unless(auth()->user()?->role === 'comercial', 403);
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $bases = BaseAsignada::with(['estado'])
            ->where('asesor_id', auth()->id())
            ->where('estado_id', $cerradoId)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('base_asignadas.cerradas', compact('bases'));
    }

    public function pendientesComercial()
    {
        abort_unless(auth()->user()?->role === 'comercial', 403);
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $bases = BaseAsignada::with(['estado'])
            ->where('asesor_id', auth()->id())
            ->where('estado_id', $pendienteId)
            ->latest('cierre_solicitado_at')
            ->paginate(20)
            ->withQueryString();

        return view('base_asignadas.pendientes_comercial', compact('bases'));
    }

    public function comercialesSupervisor()
    {
        $this->forbidIfNotSupervisor();
        $comerciales = User::where('role', 'comercial')
            ->withCount('basesAsignadas')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('base_asignadas.comerciales', compact('comerciales'));
    }

    public function gestionComercialSupervisor(Request $request, string $comercialId)
    {
        $this->forbidIfNotSupervisor();
        $comercial = User::where('role', 'comercial')->findOrFail($comercialId);
        $query = BaseAsignada::query()
            ->where('asesor_id', $comercial->id)
            ->whereNotNull('lote_uid')
            ->where('lote_uid', '!=', '')
            ->select('lote_uid', 'lote_nombre')
            ->selectRaw('count(*) as total_registros')
            ->selectRaw('count(distinct case when exists (select 1 from gestions where gestions.base_asignada_id = base_asignadas.id) then base_asignadas.id end) as gestionados')
            ->selectRaw('min(created_at) as fecha_carga')
            ->selectRaw('max(ultima_gestion_at) as ultima_modificacion')
            ->groupBy('lote_uid', 'lote_nombre')
            ->orderByDesc('fecha_carga');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where('lote_nombre', 'like', "%{$q}%");
        }

        $lotes = $query->paginate(20)->withQueryString();

        return view('base_asignadas.gestion_comercial', compact('comercial', 'lotes'));
    }

    public function gestionComercialLoteSupervisor(Request $request, string $comercialId, string $loteRef)
    {
        $this->forbidIfNotSupervisor();
        $comercial = User::where('role', 'comercial')->findOrFail($comercialId);

        $baseQuery = BaseAsignada::where('asesor_id', $comercial->id)->where('lote_uid', $loteRef);
        if (!(clone $baseQuery)->exists()) {
            $baseQuery = BaseAsignada::where('asesor_id', $comercial->id)->where('lote_nombre', $loteRef);
        }

        $query = BaseAsignada::with(['estado'])
            ->whereIn('id', (clone $baseQuery)->select('id'))
            ->orderBy('id');

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

        $bases = $query->paginate(20)->withQueryString();
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        $loteNombre = $bases->first()?->lote_nombre ?? $loteRef;

        return view('base_asignadas.gestion_comercial_lote', compact('comercial', 'bases', 'estados', 'loteNombre', 'loteRef'));
    }

    public function gestionesPendientes()
    {
        $this->forbidIfNotSupervisor();
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $bases = BaseAsignada::with(['asesor'])
            ->where('estado_id', $pendienteId)
            ->latest('cierre_solicitado_at')
            ->paginate(20)
            ->withQueryString();

        return view('base_asignadas.pendientes', compact('bases'));
    }

    public function aprobarPendiente(string $id)
    {
        $this->forbidIfNotSupervisor();
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $base = BaseAsignada::findOrFail($id);
        if ($base->estado_id !== $pendienteId) {
            return back()->withErrors(['estado_id' => 'Solo se pueden aprobar registros en estado Pendiente de aprobacion (supervisor).']);
        }
        $base->update([
            'estado_id' => $cerradoId,
            'motivo_devolucion' => null,
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $cerradoId,
            'base_asignada_id' => $base->id,
            'tipo' => 'aprobacion_supervisor',
            'detalle' => 'Aprobacion de supervisor: gestion cerrada.',
        ]);
        if ($base->asesor_id) {
            AppNotification::create([
                'user_id' => $base->asesor_id,
                'title' => 'Gestion aprobada',
                'message' => "El supervisor aprobo tu solicitud para {$base->nombre} ({$base->cedula}).",
                'type' => 'aprobacion_supervisor',
                'related_id' => $base->id,
                'related_type' => BaseAsignada::class,
                'event_at' => now(),
            ]);
        }

        return redirect()->route('base-asignada.pendientes')->with('ok', 'Gestion aprobada y marcada como cerrada.');
    }

    public function devolverPendiente(Request $request, string $id)
    {
        $this->forbidIfNotSupervisor();
        $request->validate([
            'motivo_devolucion' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $base = BaseAsignada::findOrFail($id);
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        if ($base->estado_id !== $pendienteId) {
            return back()->withErrors(['estado_id' => 'Solo se pueden devolver registros en estado Pendiente de aprobacion (supervisor).']);
        }
        $devueltaId = Estado::where('slug', 'devuelta')->value('id');
        $motivo = $request->input('motivo_devolucion');

        $base->update([
            'estado_id' => $devueltaId,
            'efectivo' => null,
            'monto_linea_credito' => null,
            'cierre_solicitado_at' => null,
            'cierre_solicitado_por' => null,
            'motivo_devolucion' => $motivo,
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $devueltaId,
            'base_asignada_id' => $base->id,
            'tipo' => 'devolucion_supervisor',
            'detalle' => "Devolucion de supervisor: {$motivo}",
        ]);
        if ($base->asesor_id) {
            AppNotification::create([
                'user_id' => $base->asesor_id,
                'title' => 'Gestion devuelta',
                'message' => "El supervisor devolvio tu gestion para {$base->nombre} ({$base->cedula}). Motivo: {$motivo}",
                'type' => 'devolucion_supervisor',
                'related_id' => $base->id,
                'related_type' => BaseAsignada::class,
                'event_at' => now(),
            ]);
        }

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
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $estado->id,
            'base_asignada_id' => $base->id,
            'tipo' => 'cambio_estado_supervisor',
            'detalle' => "Supervisor cambio estado a: {$estado->nombre}.",
        ]);

        return redirect()->route('base-asignada.show', $base->id)->with('ok', 'Estado actualizado por supervisor.');
    }

    public function reabrirAContactado(string $id)
    {
        $this->forbidIfNotSupervisor();
        $base = BaseAsignada::findOrFail($id);
        $contactadoId = Estado::where('slug', 'contactado')->value('id');
        $base->update([
            'estado_id' => $contactadoId,
            'cierre_solicitado_at' => null,
            'cierre_solicitado_por' => null,
            'motivo_devolucion' => null,
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $contactadoId,
            'base_asignada_id' => $base->id,
            'tipo' => 'reapertura_supervisor',
            'detalle' => 'Supervisor reabrio el registro y lo llevo a Contactado.',
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
        $origenesBase = self::ORIGENES_BASE;
        return view('base_asignadas.edit', compact('base', 'estados', 'supervisores', 'comerciales', 'lineasCredito', 'origenesBase'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->forbidIfNotSupervisor();
        $base = BaseAsignada::findOrFail($id);
        $data = $request->validate([
            'lote_nombre' => ['nullable', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'origen' => ['nullable', 'in:' . implode(',', self::ORIGENES_BASE)],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'asesor_id' => ['required', 'exists:users,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $nuevoLoteNombre = trim((string) ($data['lote_nombre'] ?? ''));
        if ($nuevoLoteNombre === '') {
            $data['lote_uid'] = null;
        } elseif ($nuevoLoteNombre !== (string) ($base->lote_nombre ?? '')) {
            $data['lote_uid'] = $this->buildLoteUid($nuevoLoteNombre);
        }

        $data['persona_id'] = $this->resolvePersonaId(
            $data['cedula'] ?? null,
            $data['nombre'] ?? null,
            $data['telefono'] ?? null,
            $data['email'] ?? null
        );
        // El origen se define al cargar/crear la base y no se permite cambiar luego.
        unset($data['origen']);
        if (array_key_exists('asesor_id', $data)) {
            if ($data['asesor_id'] && $data['asesor_id'] !== $base->asesor_id) {
                $data['asignado_at'] = now();
            } elseif (empty($data['asesor_id'])) {
                $data['asignado_at'] = null;
            }
        }
        $base->update($data);
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
            'lote_nombre' => ['required', 'string', 'max:255'],
            'origen' => ['required', 'in:' . implode(',', self::ORIGENES_BASE)],
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

        $permitidas = ['nombre', 'telefono', 'cedula', 'linea_credito', 'email', 'empresa', 'observaciones', 'estado_slug', 'comercial_email'];
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

        $loteNombreImport = trim((string) $request->input('lote_nombre'));
        $origenImport = trim((string) $request->input('origen'));
        if ($loteNombreImport === '') {
            fclose($file);
            return back()->withErrors(['lote_nombre' => 'Debes indicar el nombre del lote/base.']);
        }

        $creados = 0;
        $omitidos = 0;
        $importSuffix = now()->format('YmdHis') . '-' . Str::lower(Str::random(5));
        $loteUid = $this->buildLoteUid($loteNombreImport, $importSuffix);

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
            $lineaCredito = $get('linea_credito');
            $email = $get('email');
            $empresa = $get('empresa');
            $observaciones = $get('observaciones');
            $estadoSlug = $get('estado_slug');
            $comercialEmail = $get('comercial_email');

            if ($nombre === '' || $telefono === '') {
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
                'lote_uid' => $loteUid,
                'lote_nombre' => $loteNombreImport,
                'asesor_id' => $comercial?->id,
                'asignado_at' => $comercial?->id ? now() : null,
                'estado_id' => $estadoId,
                'persona_id' => $this->resolvePersonaId($cedula ?: null, $nombre, $telefono ?: null, $email ?: null),
                'nombre' => $nombre,
                'cedula' => $cedula ?: null,
                'linea_credito' => $lineaCredito ?: null,
                'telefono' => $telefono ?: null,
                'email' => $email ?: null,
                'empresa' => $empresa ?: null,
                'origen' => $origenImport,
                'observaciones' => $observaciones ?: null,
            ]);

            $creados++;
        }

        fclose($file);

        return redirect()->route('base-asignada.index')->with('ok', "Importacion completada. Creados: {$creados}. Omitidos: {$omitidos}.");
    }

    public function asignarLote(Request $request, string $loteRef)
    {
        $this->forbidIfNotSupervisor();

        $data = $request->validate([
            'comerciales' => ['required', 'array', 'min:1'],
            'comerciales.*' => ['required'],
        ]);

        $seleccion = collect($data['comerciales'])->map(fn ($v) => (string) $v)->values();
        $permiteNoAsignar = $seleccion->contains('no_asignar');
        $idsComerciales = $seleccion
            ->filter(fn ($v) => $v !== 'no_asignar')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        $comercialesValidos = User::whereIn('id', $idsComerciales)->where('role', 'comercial')->pluck('id')->values();
        if ($idsComerciales->isNotEmpty() && $comercialesValidos->count() !== $idsComerciales->count()) {
            return back()->withErrors(['comerciales' => 'Selecciona comerciales validos.']);
        }
        if ($comercialesValidos->isEmpty() && !$permiteNoAsignar) {
            return back()->withErrors(['comerciales' => 'Selecciona al menos un comercial o la opcion No asignar.']);
        }

        $baseLoteQuery = BaseAsignada::where('lote_uid', $loteRef);
        if (!(clone $baseLoteQuery)->exists()) {
            $baseLoteQuery = BaseAsignada::where('lote_nombre', $loteRef);
        }

        $registrosSinGestion = (clone $baseLoteQuery)
            ->whereDoesntHave('gestiones')
            ->orderBy('id')
            ->get();

        if ($registrosSinGestion->isEmpty()) {
            return back()->withErrors(['comerciales' => 'No hay registros disponibles para reasignar: todos tienen gestion registrada.']);
        }

        $destinos = $comercialesValidos->map(fn ($id) => (int) $id)->values();
        if ($permiteNoAsignar) {
            $destinos->push(0);
        }

        $actualizados = 0;
        DB::transaction(function () use ($registrosSinGestion, $destinos, &$actualizados) {
            foreach ($registrosSinGestion as $index => $registro) {
                $destino = $destinos[$index % $destinos->count()];
                $nuevoAsesorId = $destino === 0 ? null : $destino;
                $registro->update([
                    'asesor_id' => $nuevoAsesorId,
                    'asignado_at' => $nuevoAsesorId ? now() : null,
                    'supervisor_id' => auth()->id(),
                ]);
                $actualizados++;
            }
        });

        return redirect()->route('base-asignada.lote', ['loteRef' => $loteRef])
            ->with('ok', "Asignacion aplicada solo a registros sin gestion. Reasignados/desasignados: {$actualizados}.");
    }
}
