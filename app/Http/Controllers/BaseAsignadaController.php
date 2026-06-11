<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\BaseAsignada;
use App\Models\Estado;
use App\Models\Gestion;
use App\Models\Persona;
use App\Models\User;
use App\Models\ClientePotencial;
use App\Models\MetaComercial;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

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
        'retomado',
    ];
    private const ESTADOS_DESEMBOLSO = [
        'Por desembolsar',
        'desembolsado',
        'aplazado',
        'negado',
        'desistido',
    ];

    private function isSupervisor(): bool
    {
        return auth()->user()?->role === 'supervisor';
    }

    private function forbidIfNotSupervisor(): void
    {
        abort_unless($this->isSupervisor(), 403);
    }

    private function rolesGestores(): array
    {
        return ['comercial', 'supervisor'];
    }

    private function conteosDesembolsoUsuario(int $userId, Carbon $inicio, Carbon $fin, ?int $cerradoId): array
    {
        $estados = [
            'desembolsado' => 'desembolsado',
            'por_desembolsar' => 'por desembolsar',
            'aplazado' => 'aplazado',
            'negado' => 'negado',
            'desistido' => 'desistido',
        ];

        $conteos = array_fill_keys(array_keys($estados), 0);
        foreach (array_keys($estados) as $key) {
            $conteos["monto_{$key}"] = 0;
        }
        if (!$cerradoId) {
            return $conteos;
        }

        foreach ($estados as $key => $estado) {
            $baseQuery = BaseAsignada::query()
                ->where('asesor_id', $userId)
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = ?", [$estado])
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

            $base = (clone $baseQuery)->count();
            $baseMonto = (float) (clone $baseQuery)->sum('monto_linea_credito');

            $clienteQuery = Schema::hasColumn('cliente_potencials', 'desembolso_estado')
                ? ClientePotencial::query()
                    ->where('asesor_id', $userId)
                    ->where('estado_id', $cerradoId)
                    ->where('efectivo', true)
                    ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = ?", [$estado])
                    ->where(function ($q) use ($inicio, $fin, $cerradoId) {
                        if (Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at')) {
                            $q->whereBetween('cierre_solicitado_at', [$inicio, $fin]);
                        }
                        $q->orWhereExists(function ($sub) use ($inicio, $fin, $cerradoId) {
                            $sub->selectRaw('1')
                                ->from('gestions')
                                ->whereColumn('gestions.cliente_potencial_id', 'cliente_potencials.id')
                                ->where('gestions.estado_id', $cerradoId)
                                ->whereBetween('gestions.created_at', [$inicio, $fin]);
                        });
                    })
                : null;

            $cliente = $clienteQuery ? (clone $clienteQuery)->count() : 0;
            $clienteMonto = $clienteQuery ? (float) (clone $clienteQuery)->sum('monto_linea_credito') : 0;

            $conteos[$key] = (int) $base + (int) $cliente;
            $conteos["monto_{$key}"] = $baseMonto + $clienteMonto;
        }

        return $conteos;
    }

    private function resumenAhorrosUsuario(int $userId, Carbon $inicio, Carbon $fin, ?int $cerradoId): array
    {
        $resumen = ['cantidad' => 0, 'monto' => 0];
        if (!$cerradoId || !Schema::hasColumn('gestions', 'es_ahorro')) {
            return $resumen;
        }

        $baseQuery = Gestion::query()
            ->where('asesor_id', $userId)
            ->where('es_ahorro', true)
            ->whereBetween('gestions.created_at', [$inicio, $fin])
            ->whereNotNull('base_asignada_id')
            ->whereExists(function ($sub) use ($cerradoId) {
                $sub->selectRaw('1')
                    ->from('base_asignadas')
                    ->whereColumn('base_asignadas.id', 'gestions.base_asignada_id')
                    ->where('base_asignadas.estado_id', $cerradoId)
                    ->where('base_asignadas.efectivo', true);
            });

        $clienteQuery = Gestion::query()
            ->where('asesor_id', $userId)
            ->where('es_ahorro', true)
            ->whereBetween('gestions.created_at', [$inicio, $fin])
            ->whereNotNull('cliente_potencial_id')
            ->whereExists(function ($sub) use ($cerradoId) {
                $sub->selectRaw('1')
                    ->from('cliente_potencials')
                    ->whereColumn('cliente_potencials.id', 'gestions.cliente_potencial_id')
                    ->where('cliente_potencials.estado_id', $cerradoId)
                    ->where('cliente_potencials.efectivo', true);
            });

        $resumen['cantidad'] = (int) (clone $baseQuery)->distinct('base_asignada_id')->count('base_asignada_id')
            + (int) (clone $clienteQuery)->distinct('cliente_potencial_id')->count('cliente_potencial_id');

        if (Schema::hasColumn('gestions', 'monto_ahorro')) {
            $resumen['monto'] = (float) (clone $baseQuery)->sum('monto_ahorro')
                + (float) (clone $clienteQuery)->sum('monto_ahorro');
        }

        return $resumen;
    }

    private function estadosGestionables()
    {
        return Estado::where('activo', true)
            ->whereNotIn('slug', ['pendiente-aprobacion-supervisor', 'devuelta', 'efectiva'])
            ->orderBy('nombre')
            ->get();
    }

    private function estadosAsignablesEnCarga()
    {
        return Estado::where('activo', true)
            ->whereNotIn('slug', ['pendiente-aprobacion-supervisor'])
            ->orderBy('nombre')
            ->get();
    }

    private function estadoBloqueadoParaCarga(?Estado $estado): bool
    {
        return in_array($estado?->slug, ['pendiente-aprobacion-supervisor'], true);
    }

    private function estadoNuevoId(): int
    {
        $estadoId = Estado::where('slug', 'nuevo')->value('id');
        abort_unless($estadoId, 500, 'No existe el estado Nuevo en la base de datos.');

        return (int) $estadoId;
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

    private function resumenRendimientoUsuario(User $comercial, Carbon $inicio, Carbon $fin, ?int $cerradoId)
    {
        $basePeriodo = BaseAsignada::query()
            ->where('asesor_id', $comercial->id)
            ->whereBetween('created_at', [$inicio, $fin]);
        $cpPeriodo = ClientePotencial::query()
            ->where('asesor_id', $comercial->id)
            ->whereBetween('created_at', [$inicio, $fin]);

        $asignados = (clone $basePeriodo)->count() + (clone $cpPeriodo)->count();
        $gestionados = BaseAsignada::query()
            ->where('asesor_id', $comercial->id)
            ->whereHas('gestiones', fn ($g) => $g->whereBetween('created_at', [$inicio, $fin]))
            ->count()
            + ClientePotencial::query()
                ->where('asesor_id', $comercial->id)
                ->whereHas('gestiones', fn ($g) => $g->whereBetween('created_at', [$inicio, $fin]))
                ->count();

        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $pendientes = $pendienteId
            ? (BaseAsignada::query()->where('asesor_id', $comercial->id)->where('estado_id', $pendienteId)->whereBetween('created_at', [$inicio, $fin])->count()
                + ClientePotencial::query()->where('asesor_id', $comercial->id)->where('estado_id', $pendienteId)->whereBetween('created_at', [$inicio, $fin])->count())
            : 0;

        $cierresBase = BaseAsignada::query()
            ->where('asesor_id', $comercial->id)
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
        $cierresCp = ClientePotencial::query()
            ->where('asesor_id', $comercial->id)
            ->where('estado_id', $cerradoId)
            ->where(function ($q) use ($inicio, $fin, $cerradoId) {
                if (Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at')) {
                    $q->whereBetween('cierre_solicitado_at', [$inicio, $fin]);
                }
                $q->orWhereExists(function ($sub) use ($inicio, $fin, $cerradoId) {
                    $sub->selectRaw('1')
                        ->from('gestions')
                        ->whereColumn('gestions.cliente_potencial_id', 'cliente_potencials.id')
                        ->where('gestions.estado_id', $cerradoId)
                        ->whereBetween('gestions.created_at', [$inicio, $fin]);
                });
            });

        $cierres = (clone $cierresBase)->count() + (clone $cierresCp)->count();
        $comercial->total_registros = $asignados;
        $comercial->asignados_mes = $asignados;
        $comercial->gestionados_registros = $gestionados;
        $comercial->pendientes_registros = $pendientes;
        $comercial->cierres_mes = $cierres;
        $comercial->porcentaje_cierre_vs_asignados = $asignados > 0 ? round(($cierres / $asignados) * 100, 1) : 0;
        $comercial->monto_colocado_mes = (float) (clone $cierresBase)->sum('monto_linea_credito') + (float) (clone $cierresCp)->sum('monto_linea_credito');
        $comercial->monto_solicitado_mes = (float) (clone $basePeriodo)->sum('monto_solicitado') + (float) (clone $cpPeriodo)->sum('monto_solicitado');
        $comercial->total_llamadas_mes = (int) Gestion::query()
            ->where('asesor_id', $comercial->id)
            ->whereRaw("LOWER(TRIM(tipo)) = 'llamada'")
            ->whereBetween('created_at', [$inicio, $fin])
            ->count();
        $resumenAhorros = $this->resumenAhorrosUsuario((int) $comercial->id, $inicio, $fin, $cerradoId);
        $comercial->ahorros_mes = $resumenAhorros['cantidad'];
        $comercial->monto_ahorros_mes = $resumenAhorros['monto'];
        $comercial->tiempo_invertido_min_mes = (int) Gestion::query()
            ->where('asesor_id', $comercial->id)
            ->whereBetween('created_at', [$inicio, $fin])
            ->sum('minutos_invertidos');
        $comercial->efectivo_si_mes = (int) (clone $cierresBase)->where('efectivo', true)->count() + (int) (clone $cierresCp)->where('efectivo', true)->count();
        $comercial->efectivo_no_mes = (int) (clone $cierresBase)->where('efectivo', false)->count() + (int) (clone $cierresCp)->where('efectivo', false)->count();
        $conteosDesembolso = $this->conteosDesembolsoUsuario((int) $comercial->id, $inicio, $fin, $cerradoId);
        $comercial->desembolso_desembolsado_mes = $conteosDesembolso['desembolsado'];
        $comercial->desembolso_por_desembolsar_mes = $conteosDesembolso['por_desembolsar'];
        $comercial->desembolso_aplazado_mes = $conteosDesembolso['aplazado'];
        $comercial->desembolso_negado_mes = $conteosDesembolso['negado'];
        $comercial->desembolso_desistido_mes = $conteosDesembolso['desistido'];
        $comercial->monto_desembolso_desembolsado_mes = $conteosDesembolso['monto_desembolsado'];
        $comercial->monto_desembolso_por_desembolsar_mes = $conteosDesembolso['monto_por_desembolsar'];
        $comercial->monto_desembolso_aplazado_mes = $conteosDesembolso['monto_aplazado'];
        $comercial->monto_desembolso_negado_mes = $conteosDesembolso['monto_negado'];
        $comercial->monto_desembolso_desistido_mes = $conteosDesembolso['monto_desistido'];
        $comercial->monto_desembolsado_mes = (float) BaseAsignada::query()
            ->where('asesor_id', $comercial->id)
            ->where('estado_id', $cerradoId)
            ->where('efectivo', true)
            ->whereNotNull('cierre_aprobado_at')
            ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
            ->whereBetween('desembolso_aprobado_at', [$inicio, $fin])
            ->sum('monto_linea_credito')
            + (float) ClientePotencial::query()
                ->where('asesor_id', $comercial->id)
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->whereNotNull('cierre_aprobado_at')
                ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                ->whereBetween('desembolso_aprobado_at', [$inicio, $fin])
                ->sum('monto_linea_credito');

        $mins = [];
        foreach ((clone $cierresBase)->get(['asignado_at', 'created_at', 'cierre_solicitado_at']) as $cierreBase) {
            $inicioRegistro = $cierreBase->asignado_at ?: $cierreBase->created_at;
            $finRegistro = $cierreBase->cierre_solicitado_at ?: $cierreBase->created_at;
            if ($inicioRegistro && $finRegistro) {
                $mins[] = Carbon::parse($inicioRegistro)->diffInMinutes(Carbon::parse($finRegistro));
            }
        }
        $comercial->prom_tiempo_cierre_min = count($mins) > 0 ? (int) round(array_sum($mins) / count($mins)) : 0;

        return $comercial;
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
        $isSupervisor = $this->isSupervisor();
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
        if (request()->filled('origen')) {
            $origen = trim((string) request('origen'));
            $query->where('origen', $origen);
        }
        if (request()->filled('mes')) {
            $mesFiltro = max(1, min(12, (int) request('mes')));
            $anioFiltro = request()->filled('anio')
                ? max(2026, min(2036, (int) request('anio')))
                : (int) now()->year;
            $inicioFiltro = Carbon::create($anioFiltro, $mesFiltro, 1)->startOfMonth();
            $finFiltro = (clone $inicioFiltro)->endOfMonth();
            $query->whereBetween('created_at', [$inicioFiltro, $finFiltro]);
        } elseif (request()->filled('anio')) {
            $anioFiltro = max(2026, min(2036, (int) request('anio')));
            $query->whereBetween('created_at', [
                Carbon::create($anioFiltro, 1, 1)->startOfYear(),
                Carbon::create($anioFiltro, 12, 31)->endOfYear(),
            ]);
        }

        if (!$isSupervisor) {
            $query->where('asesor_id', auth()->id());
        }

        $rows = $query->groupBy('lote_uid', 'lote_nombre')->orderBy('lote_nombre')->get();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $lotes = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
        $lotes->each(function ($lote) {
            $total = (int) $lote->total;
            $gestionados = (int) $lote->gestionados;
            $lote->porcentaje_gestion = $total > 0 ? round(($gestionados / $total) * 100, 1) : 0;
        });
        $estados = collect();
        $supervisores = collect();
        $comerciales = collect();
        $lineasCredito = [];
        $origenesBase = [];
        if ($isSupervisor) {
            $estados = $this->estadosAsignablesEnCarga();
            $supervisores = User::where('role', 'supervisor')->orderBy('name')->get();
            $comerciales = User::whereIn('role', $this->rolesGestores())->orderBy('name')->get();
            $lineasCredito = self::LINEAS_CREDITO;
            $origenesBase = self::ORIGENES_BASE;
        }

        return view('base_asignadas.index', compact(
            'lotes',
            'isSupervisor',
            'estados',
            'supervisores',
            'comerciales',
            'lineasCredito',
            'origenesBase'
        ));
    }

    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $esSupervisor = $this->isSupervisor();
        $asesoresFiltro = $esSupervisor
            ? User::whereIn('role', $this->rolesGestores())->orderBy('name')->get()
            : collect();
        $asesorFiltroId = null;
        if ($esSupervisor && $request->filled('asesor_id')) {
            $asesorIdRequest = (int) $request->input('asesor_id');
            if ($asesoresFiltro->contains('id', $asesorIdRequest)) {
                $asesorFiltroId = $asesorIdRequest;
            }
        }
        $clienteTieneCamposCierre = Schema::hasColumns('cliente_potencials', [
            'efectivo',
            'monto_linea_credito',
            'cierre_solicitado_at',
        ]);
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $devueltaId = Estado::where('slug', 'devuelta')->value('id');

        $baseQuery = BaseAsignada::query();
        if (!$esSupervisor) {
            $baseQuery->where('asesor_id', $user?->id);
        } elseif ($asesorFiltroId) {
            $baseQuery->where('asesor_id', $asesorFiltroId);
        }

        $basePorLote = BaseAsignada::query();
        if (!$esSupervisor) {
            $basePorLote->where('asesor_id', $user?->id);
        } elseif ($asesorFiltroId) {
            $basePorLote->where('asesor_id', $asesorFiltroId);
        }
        $clienteQuery = ClientePotencial::query();
        if (!$esSupervisor) {
            $clienteQuery->where('asesor_id', $user?->id);
        } elseif ($asesorFiltroId) {
            $clienteQuery->where('asesor_id', $asesorFiltroId);
        }

        $mes = max(1, min(12, (int) $request->input('mes', now()->month)));
        $anio = (int) $request->input('anio', now()->year);
        $periodo = in_array($request->input('periodo'), ['mes', 'anio'], true)
            ? (string) $request->input('periodo')
            : 'mes';
        if ($anio < 2026 || $anio > 2036) {
            $anio = max(2026, min(2036, (int) now()->year));
        }
        if ($periodo === 'anio') {
            $inicioMes = Carbon::create($anio, 1, 1)->startOfYear();
            $finMes = (clone $inicioMes)->endOfYear();
            $inicioMesAnterior = (clone $inicioMes)->subYearNoOverflow()->startOfYear();
            $finMesAnterior = (clone $inicioMesAnterior)->endOfYear();
        } else {
            $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
            $finMes = (clone $inicioMes)->endOfMonth();
            $inicioMesAnterior = (clone $inicioMes)->subMonthNoOverflow()->startOfMonth();
            $finMesAnterior = (clone $inicioMesAnterior)->endOfMonth();
        }
        $periodoTexto = $periodo === 'anio' ? 'año seleccionado' : 'mes seleccionado';
        $periodoActualTitulo = $periodo === 'anio'
            ? (string) $anio
            : $inicioMes->locale('es')->translatedFormat('F Y');
        $periodoAnteriorTitulo = $periodo === 'anio'
            ? (string) ($anio - 1)
            : $inicioMesAnterior->locale('es')->translatedFormat('F Y');

        $totalBases = (clone $baseQuery)->count() + (clone $clienteQuery)->count();
        $gestionadas = (clone $baseQuery)->whereHas('gestiones')->count() + (clone $clienteQuery)->whereHas('gestiones')->count();
        $pendientesAprobacion = $pendienteId
            ? ((clone $baseQuery)->where('estado_id', $pendienteId)->count() + (clone $clienteQuery)->where('estado_id', $pendienteId)->count())
            : 0;
        $cerradas = $cerradoId
            ? ((clone $baseQuery)->where('estado_id', $cerradoId)->count() + (clone $clienteQuery)->where('estado_id', $cerradoId)->count())
            : 0;
        $cerradasNoEfectivas = $cerradoId
            ? ((clone $baseQuery)->where('estado_id', $cerradoId)->where('efectivo', false)->count() + ($clienteTieneCamposCierre ? (clone $clienteQuery)->where('estado_id', $cerradoId)->where('efectivo', false)->count() : 0))
            : 0;
        $devueltas = $devueltaId
            ? ((clone $baseQuery)->where('estado_id', $devueltaId)->count() + (clone $clienteQuery)->where('estado_id', $devueltaId)->count())
            : 0;
        $montoCerrado = $cerradoId
            ? ((clone $baseQuery)->where('estado_id', $cerradoId)->sum('monto_linea_credito') + ($clienteTieneCamposCierre ? (clone $clienteQuery)->where('estado_id', $cerradoId)->sum('monto_linea_credito') : 0))
            : 0;
        $montoDesembolsado = $cerradoId
            ? ((clone $baseQuery)
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->whereNotNull('cierre_aprobado_at')
                ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                ->whereNotNull('desembolso_aprobado_at')
                ->sum('monto_linea_credito')
                + ($clienteTieneCamposCierre ? (clone $clienteQuery)
                    ->where('estado_id', $cerradoId)
                    ->where('efectivo', true)
                    ->whereNotNull('cierre_aprobado_at')
                    ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                    ->whereNotNull('desembolso_aprobado_at')
                    ->sum('monto_linea_credito') : 0))
            : 0;

        $porcentajeGestion = $totalBases > 0 ? round(($gestionadas / $totalBases) * 100, 1) : 0;
        $porcentajeCierre = $totalBases > 0 ? round(($cerradas / $totalBases) * 100, 1) : 0;

        $metricasPeriodo = function (Carbon $inicio, Carbon $fin) use ($basePorLote, $clienteQuery, $cerradoId, $pendienteId, $clienteTieneCamposCierre) {
            $registrosCargadosPeriodo = (clone $basePorLote)
                ->whereBetween('created_at', [$inicio, $fin])
                ->count()
                + (clone $clienteQuery)->whereBetween('created_at', [$inicio, $fin])->count();
            $baseIdsPeriodo = (clone $basePorLote)->select('id');
            $clientesIdsPeriodo = (clone $clienteQuery)->select('id');
            $registrosGestionadosBase = Gestion::query()
                ->whereIn('base_asignada_id', $baseIdsPeriodo)
                ->whereBetween('created_at', [$inicio, $fin])
                ->distinct('base_asignada_id')
                ->count('base_asignada_id');
            $registrosGestionadosClientes = Gestion::query()
                ->whereIn('cliente_potencial_id', $clientesIdsPeriodo)
                ->whereBetween('created_at', [$inicio, $fin])
                ->distinct('cliente_potencial_id')
                ->count('cliente_potencial_id');
            $registrosGestionadosPeriodo = $registrosGestionadosBase + $registrosGestionadosClientes;
            $totalLlamadasPeriodo = Gestion::query()
                ->whereRaw("LOWER(TRIM(tipo)) = 'llamada'")
                ->whereBetween('created_at', [$inicio, $fin])
                ->where(function ($q) use ($basePorLote, $clienteQuery) {
                    $q->whereIn('base_asignada_id', (clone $basePorLote)->select('id'))
                        ->orWhereIn('cliente_potencial_id', (clone $clienteQuery)->select('id'));
                })
                ->count();
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
            $cierresClientesPeriodo = null;
            if ($clienteTieneCamposCierre) {
                $cierresClientesPeriodo = (clone $clienteQuery)
                    ->where('estado_id', $cerradoId)
                    ->where(function ($q) use ($inicio, $fin, $cerradoId) {
                        $q->whereBetween('cierre_solicitado_at', [$inicio, $fin])
                            ->orWhereExists(function ($sub) use ($inicio, $fin, $cerradoId) {
                                $sub->selectRaw('1')
                                    ->from('gestions')
                                    ->whereColumn('gestions.cliente_potencial_id', 'cliente_potencials.id')
                                    ->where('gestions.estado_id', $cerradoId)
                                    ->whereBetween('gestions.created_at', [$inicio, $fin]);
                            });
                    });
            }

            $cierresPeriodo = (clone $cierresBasePeriodo)->count() + ($cierresClientesPeriodo ? (clone $cierresClientesPeriodo)->count() : 0);
            $cierresNoEfectivosPeriodo = (clone $cierresBasePeriodo)
                ->where('efectivo', false)
                ->count()
                + ($cierresClientesPeriodo ? (clone $cierresClientesPeriodo)->where('efectivo', false)->count() : 0);
            $montoPeriodo = (clone $cierresBasePeriodo)->sum('monto_linea_credito')
                + ($cierresClientesPeriodo ? (clone $cierresClientesPeriodo)->sum('monto_linea_credito') : 0);
            $montoSolicitadoPeriodo = (clone $cierresBasePeriodo)->sum('monto_solicitado')
                + ($cierresClientesPeriodo ? (clone $cierresClientesPeriodo)->sum('monto_solicitado') : 0);
            $montoDesembolsadoPeriodo = (clone $basePorLote)
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->whereNotNull('cierre_aprobado_at')
                ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                ->whereBetween('desembolso_aprobado_at', [$inicio, $fin])
                ->sum('monto_linea_credito')
                + ($clienteTieneCamposCierre ? (clone $clienteQuery)
                    ->where('estado_id', $cerradoId)
                    ->where('efectivo', true)
                    ->whereNotNull('cierre_aprobado_at')
                    ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                    ->whereBetween('desembolso_aprobado_at', [$inicio, $fin])
                    ->sum('monto_linea_credito') : 0);
            $desembolsosPeriodo = (clone $basePorLote)
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->whereNotNull('cierre_aprobado_at')
                ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                ->whereBetween('desembolso_aprobado_at', [$inicio, $fin])
                ->count()
                + ($clienteTieneCamposCierre ? (clone $clienteQuery)
                    ->where('estado_id', $cerradoId)
                    ->where('efectivo', true)
                    ->whereNotNull('cierre_aprobado_at')
                    ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                    ->whereBetween('desembolso_aprobado_at', [$inicio, $fin])
                    ->count() : 0);
            $vinculacionesPeriodo = 0;
            $ahorrosPeriodo = 0;
            $montoAhorrosPeriodo = 0;
            if (Schema::hasColumns('gestions', ['es_vinculacion', 'base_asignada_id', 'cliente_potencial_id'])) {
                $baseVinculaciones = Gestion::query()
                    ->where('es_vinculacion', true)
                    ->where('estado_id', $pendienteId)
                    ->whereBetween('gestions.created_at', [$inicio, $fin])
                    ->whereIn('base_asignada_id', (clone $basePorLote)->select('id'))
                    ->whereExists(function ($sub) use ($cerradoId) {
                        $sub->selectRaw('1')
                            ->from('base_asignadas')
                            ->whereColumn('base_asignadas.id', 'gestions.base_asignada_id')
                            ->where('base_asignadas.estado_id', $cerradoId)
                            ->where('base_asignadas.efectivo', true);
                    })
                    ->distinct('base_asignada_id')
                    ->count('base_asignada_id');

                $clienteVinculaciones = $clienteTieneCamposCierre
                    ? Gestion::query()
                        ->where('es_vinculacion', true)
                        ->where('estado_id', $pendienteId)
                        ->whereBetween('gestions.created_at', [$inicio, $fin])
                        ->whereIn('cliente_potencial_id', (clone $clienteQuery)->select('id'))
                        ->whereExists(function ($sub) use ($cerradoId) {
                            $sub->selectRaw('1')
                                ->from('cliente_potencials')
                                ->whereColumn('cliente_potencials.id', 'gestions.cliente_potencial_id')
                                ->where('cliente_potencials.estado_id', $cerradoId)
                                ->where('cliente_potencials.efectivo', true);
                        })
                        ->distinct('cliente_potencial_id')
                        ->count('cliente_potencial_id')
                    : 0;

                $vinculacionesPeriodo = $baseVinculaciones + $clienteVinculaciones;

                if (Schema::hasColumn('gestions', 'es_ahorro')) {
                    $baseAhorrosQuery = Gestion::query()
                        ->where('es_ahorro', true)
                        ->where('estado_id', $pendienteId)
                        ->whereBetween('gestions.created_at', [$inicio, $fin])
                        ->whereIn('base_asignada_id', (clone $basePorLote)->select('id'))
                        ->whereExists(function ($sub) use ($cerradoId) {
                            $sub->selectRaw('1')
                                ->from('base_asignadas')
                                ->whereColumn('base_asignadas.id', 'gestions.base_asignada_id')
                                ->where('base_asignadas.estado_id', $cerradoId)
                                ->where('base_asignadas.efectivo', true);
                        });

                    $baseAhorros = (clone $baseAhorrosQuery)
                        ->distinct('base_asignada_id')
                        ->count('base_asignada_id');
                    $baseMontoAhorros = Schema::hasColumn('gestions', 'monto_ahorro')
                        ? (float) (clone $baseAhorrosQuery)->sum('monto_ahorro')
                        : 0;

                    $clienteAhorrosQuery = $clienteTieneCamposCierre
                        ? Gestion::query()
                            ->where('es_ahorro', true)
                            ->where('estado_id', $pendienteId)
                            ->whereBetween('gestions.created_at', [$inicio, $fin])
                            ->whereIn('cliente_potencial_id', (clone $clienteQuery)->select('id'))
                            ->whereExists(function ($sub) use ($cerradoId) {
                                $sub->selectRaw('1')
                                    ->from('cliente_potencials')
                                    ->whereColumn('cliente_potencials.id', 'gestions.cliente_potencial_id')
                                    ->where('cliente_potencials.estado_id', $cerradoId)
                                    ->where('cliente_potencials.efectivo', true);
                            })
                        : null;

                    $clienteAhorros = $clienteAhorrosQuery
                        ? (clone $clienteAhorrosQuery)->distinct('cliente_potencial_id')->count('cliente_potencial_id')
                        : 0;
                    $clienteMontoAhorros = ($clienteAhorrosQuery && Schema::hasColumn('gestions', 'monto_ahorro'))
                        ? (float) (clone $clienteAhorrosQuery)->sum('monto_ahorro')
                        : 0;

                    $ahorrosPeriodo = $baseAhorros + $clienteAhorros;
                    $montoAhorrosPeriodo = $baseMontoAhorros + $clienteMontoAhorros;
                }
            }

            $porcentajeGestionPeriodo = $registrosCargadosPeriodo > 0
                ? round(($registrosGestionadosPeriodo / $registrosCargadosPeriodo) * 100, 1)
                : 0;
            $porcentajeCierrePeriodo = $registrosGestionadosPeriodo > 0
                ? round(($cierresPeriodo / $registrosGestionadosPeriodo) * 100, 1)
                : 0;
            return [
                'registros_cargados' => $registrosCargadosPeriodo,
                'registros_gestionados' => $registrosGestionadosPeriodo,
                'total_llamadas' => $totalLlamadasPeriodo,
                'porcentaje_gestion' => $porcentajeGestionPeriodo,
                'cierres' => $cierresPeriodo,
                'cierres_no_efectivos' => $cierresNoEfectivosPeriodo,
                'porcentaje_cierre' => $porcentajeCierrePeriodo,
                'monto' => $montoPeriodo,
                'monto_solicitado' => $montoSolicitadoPeriodo,
                'monto_desembolsado' => $montoDesembolsadoPeriodo,
                'desembolsos' => $desembolsosPeriodo,
                'vinculaciones' => $vinculacionesPeriodo,
                'ahorros' => $ahorrosPeriodo,
                'monto_ahorros' => $montoAhorrosPeriodo,
            ];
        };

        $kpiMesActual = $metricasPeriodo($inicioMes, $finMes);
        $kpiMesAnterior = $metricasPeriodo($inicioMesAnterior, $finMesAnterior);
        $tiempoInvertidoMesMin = 0;
        $promTiempoCierreMesMin = 0;
        $efectivoSiMes = 0;
        $efectivoNoMes = 0;
        $promPrimeraGestionMesMin = 0;
        $promAprobacionSupervisorMesMin = 0;

        $baseIdsScope = (clone $baseQuery)->select('id');
        $promPrimera = Gestion::query()
            ->joinSub(
                Gestion::query()
                    ->selectRaw('base_asignada_id, min(created_at) as primera_gestion_at')
                    ->groupBy('base_asignada_id'),
                'pg',
                'pg.base_asignada_id',
                '=',
                'gestions.base_asignada_id'
            )
            ->join('base_asignadas', 'base_asignadas.id', '=', 'pg.base_asignada_id')
            ->whereIn('base_asignadas.id', $baseIdsScope)
            ->whereColumn('gestions.created_at', 'pg.primera_gestion_at')
            ->whereBetween('pg.primera_gestion_at', [$inicioMes, $finMes])
            ->selectRaw('avg(timestampdiff(minute, COALESCE(base_asignadas.asignado_at, base_asignadas.created_at), pg.primera_gestion_at)) as prom_min')
            ->value('prom_min');
        $promPrimeraGestionMesMin = (int) round((float) ($promPrimera ?? 0));

        $promAprob = BaseAsignada::query()
            ->whereIn('id', $baseIdsScope)
            ->whereNotNull('cierre_solicitado_at')
            ->whereNotNull('cierre_aprobado_at')
            ->whereBetween('cierre_aprobado_at', [$inicioMes, $finMes])
            ->selectRaw('avg(timestampdiff(minute, cierre_solicitado_at, cierre_aprobado_at)) as prom_min')
            ->value('prom_min');
        $promAprobacionSupervisorMesMin = (int) round((float) ($promAprob ?? 0));
        if (!$esSupervisor && $user) {
            $tiempoInvertidoMesMin = (int) Gestion::query()
                ->where('asesor_id', $user->id)
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
        $canalesBaseRaw = Gestion::query()
            ->join('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
            ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
            ->when(!$esSupervisor, function ($q) use ($user) {
                $q->where('base_asignadas.asesor_id', $user?->id);
            })
            ->when($asesorFiltroId, fn ($q) => $q->where('base_asignadas.asesor_id', $asesorFiltroId))
            ->selectRaw("LOWER(TRIM(base_asignadas.origen)) as canal")
            ->selectRaw('count(distinct gestions.base_asignada_id) as total_gestiones')
            ->selectRaw('count(distinct gestions.base_asignada_id) as registros_unicos')
            ->groupByRaw("LOWER(TRIM(base_asignadas.origen))")
            ->get();

        $canalesClientesRaw = Gestion::query()
            ->join('cliente_potencials', 'cliente_potencials.id', '=', 'gestions.cliente_potencial_id')
            ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
            ->when(!$esSupervisor, function ($q) use ($user) {
                $q->where('cliente_potencials.asesor_id', $user?->id);
            })
            ->when($asesorFiltroId, fn ($q) => $q->where('cliente_potencials.asesor_id', $asesorFiltroId))
            ->selectRaw("LOWER(TRIM(cliente_potencials.fuente)) as canal")
            ->selectRaw('count(distinct gestions.cliente_potencial_id) as total_gestiones')
            ->selectRaw('count(distinct gestions.cliente_potencial_id) as registros_unicos')
            ->groupByRaw("LOWER(TRIM(cliente_potencials.fuente))")
            ->get();

        $canalesMes = $canalesBaseRaw
            ->concat($canalesClientesRaw)
            ->groupBy('canal')
            ->map(function ($rows, $canal) {
                return (object) [
                    'canal' => $canal,
                    'total_gestiones' => (int) $rows->sum('total_gestiones'),
                    'registros_unicos' => (int) $rows->sum('registros_unicos'),
                ];
            })
            ->sortByDesc('total_gestiones')
            ->values()
            ->filter(fn ($r) => in_array($r->canal, $canalesPermitidos, true))
            ->values();

        $cierresCanalBaseRaw = Gestion::query()
            ->join('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
            ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
            ->where('gestions.estado_id', $pendienteId)
            ->when(!$esSupervisor, function ($q) use ($user) {
                $q->where('base_asignadas.asesor_id', $user?->id);
            })
            ->when($asesorFiltroId, fn ($q) => $q->where('base_asignadas.asesor_id', $asesorFiltroId))
            ->selectRaw("LOWER(TRIM(base_asignadas.origen)) as canal")
            ->selectRaw('count(*) as solicitudes_cierre')
            ->selectRaw('count(distinct gestions.base_asignada_id) as registros_unicos')
            ->selectRaw('sum(case when base_asignadas.estado_id = ? then 1 else 0 end) as cierres_aprobados', [$cerradoId ?? 0])
            ->selectRaw('sum(case when base_asignadas.estado_id = ? and base_asignadas.efectivo = 0 then 1 else 0 end) as cierres_no_efectivos', [$cerradoId ?? 0])
            ->selectRaw('sum(COALESCE(base_asignadas.monto_solicitado, 0)) as monto_solicitado')
            ->selectRaw('sum(case when base_asignadas.estado_id = ? then COALESCE(base_asignadas.monto_linea_credito, 0) else 0 end) as monto_aprobado', [$cerradoId ?? 0])
            ->selectRaw("sum(case when base_asignadas.estado_id = ? and base_asignadas.efectivo = 1 and base_asignadas.cierre_aprobado_at is not null and base_asignadas.desembolso_aprobado_at is not null and LOWER(TRIM(COALESCE(base_asignadas.desembolso_estado, ''))) = 'desembolsado' then COALESCE(base_asignadas.monto_linea_credito, 0) else 0 end) as monto_desembolsado", [$cerradoId ?? 0])
            ->groupByRaw("LOWER(TRIM(base_asignadas.origen))")
            ->get();

        $cierresCanalClientesRaw = Gestion::query()
            ->join('cliente_potencials', 'cliente_potencials.id', '=', 'gestions.cliente_potencial_id')
            ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
            ->where('gestions.estado_id', $pendienteId)
            ->when(!$esSupervisor, function ($q) use ($user) {
                $q->where('cliente_potencials.asesor_id', $user?->id);
            })
            ->when($asesorFiltroId, fn ($q) => $q->where('cliente_potencials.asesor_id', $asesorFiltroId))
            ->selectRaw("LOWER(TRIM(cliente_potencials.fuente)) as canal")
            ->selectRaw('count(*) as solicitudes_cierre')
            ->selectRaw('count(distinct gestions.cliente_potencial_id) as registros_unicos')
            ->selectRaw('sum(case when cliente_potencials.estado_id = ? then 1 else 0 end) as cierres_aprobados', [$cerradoId ?? 0])
            ->selectRaw('sum(case when cliente_potencials.estado_id = ? and cliente_potencials.efectivo = 0 then 1 else 0 end) as cierres_no_efectivos', [$cerradoId ?? 0])
            ->selectRaw('sum(COALESCE(cliente_potencials.monto_solicitado, 0)) as monto_solicitado')
            ->selectRaw('sum(case when cliente_potencials.estado_id = ? then COALESCE(cliente_potencials.monto_linea_credito, 0) else 0 end) as monto_aprobado', [$cerradoId ?? 0])
            ->selectRaw("sum(case when cliente_potencials.estado_id = ? and cliente_potencials.efectivo = 1 and cliente_potencials.cierre_aprobado_at is not null and cliente_potencials.desembolso_aprobado_at is not null and LOWER(TRIM(COALESCE(cliente_potencials.desembolso_estado, ''))) = 'desembolsado' then COALESCE(cliente_potencials.monto_linea_credito, 0) else 0 end) as monto_desembolsado", [$cerradoId ?? 0])
            ->groupByRaw("LOWER(TRIM(cliente_potencials.fuente))")
            ->get();

        $cierresCanalMes = $cierresCanalBaseRaw
            ->concat($cierresCanalClientesRaw)
            ->groupBy('canal')
            ->map(function ($rows, $canal) {
                return (object) [
                    'canal' => $canal,
                    'solicitudes_cierre' => (int) $rows->sum('solicitudes_cierre'),
                    'registros_unicos' => (int) $rows->sum('registros_unicos'),
                    'cierres_aprobados' => (int) $rows->sum('cierres_aprobados'),
                    'cierres_no_efectivos' => (int) $rows->sum('cierres_no_efectivos'),
                    'monto_solicitado' => (float) $rows->sum('monto_solicitado'),
                    'monto_aprobado' => (float) $rows->sum('monto_aprobado'),
                    'monto_desembolsado' => (float) $rows->sum('monto_desembolsado'),
                ];
            })
            ->sortByDesc('solicitudes_cierre')
            ->values()
            ->filter(fn ($r) => in_array($r->canal, $canalesPermitidos, true))
            ->values();

        $estadosBase = (clone $baseQuery)
            ->leftJoin('estados', 'estados.id', '=', 'base_asignadas.estado_id')
            ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
            ->selectRaw('count(*) as total')
            ->groupBy('estados.nombre')
            ->get();
        $estadosClientes = (clone $clienteQuery)
            ->leftJoin('estados', 'estados.id', '=', 'cliente_potencials.estado_id')
            ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
            ->selectRaw('count(*) as total')
            ->groupBy('estados.nombre')
            ->get();
        $estadosResumen = $estadosBase
            ->concat($estadosClientes)
            ->groupBy('estado_nombre')
            ->map(fn ($rows, $estado) => (object) ['estado_nombre' => $estado, 'total' => (int) $rows->sum('total')])
            ->sortByDesc('total')
            ->values();

        $desembolsoResumen = collect();
        $desembolsoEfectivosResumen = collect();
        if ($cerradoId && Schema::hasColumn('base_asignadas', 'desembolso_estado') && Schema::hasColumn('cliente_potencials', 'desembolso_estado')) {
            $baseDesembolso = BaseAsignada::query()
                ->where('estado_id', $cerradoId)
                ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id))
                ->when($asesorFiltroId, fn ($q) => $q->where('asesor_id', $asesorFiltroId))
                ->where(function ($q) use ($inicioMes, $finMes) {
                    $q->whereBetween('cierre_aprobado_at', [$inicioMes, $finMes])
                        ->orWhereBetween('cierre_solicitado_at', [$inicioMes, $finMes]);
                })
                ->selectRaw("COALESCE(desembolso_estado, 'Sin estado desembolso') as desembolso_estado")
                ->selectRaw("case when efectivo = 1 then 'efectivo' else 'no_efectivo' end as grupo")
                ->selectRaw('count(*) as total')
                ->groupBy('desembolso_estado', 'grupo')
                ->get();
            $clienteDesembolso = ClientePotencial::query()
                ->where('estado_id', $cerradoId)
                ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id))
                ->when($asesorFiltroId, fn ($q) => $q->where('asesor_id', $asesorFiltroId))
                ->where(function ($q) use ($inicioMes, $finMes) {
                    $q->whereBetween('cierre_aprobado_at', [$inicioMes, $finMes])
                        ->orWhereBetween('cierre_solicitado_at', [$inicioMes, $finMes]);
                })
                ->selectRaw("COALESCE(desembolso_estado, 'Sin estado desembolso') as desembolso_estado")
                ->selectRaw("case when efectivo = 1 then 'efectivo' else 'no_efectivo' end as grupo")
                ->selectRaw('count(*) as total')
                ->groupBy('desembolso_estado', 'grupo')
                ->get();

            $desembolsoResumen = $baseDesembolso
                ->concat($clienteDesembolso)
                ->groupBy('desembolso_estado')
                ->map(function ($rows, $estado) {
                    $efectivos = (int) $rows->where('grupo', 'efectivo')->sum('total');
                    $noEfectivos = (int) $rows->where('grupo', 'no_efectivo')->sum('total');
                    return (object) [
                        'desembolso_estado' => $estado,
                        'efectivos' => $efectivos,
                        'no_efectivos' => $noEfectivos,
                        'total' => $efectivos + $noEfectivos,
                    ];
                })
                ->sortByDesc('total')
                ->values();

            $baseDesembolsoEfectivos = BaseAsignada::query()
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id))
                ->when($asesorFiltroId, fn ($q) => $q->where('asesor_id', $asesorFiltroId))
                ->where(function ($q) use ($inicioMes, $finMes) {
                    $q->whereBetween('cierre_aprobado_at', [$inicioMes, $finMes])
                        ->orWhereBetween('cierre_solicitado_at', [$inicioMes, $finMes]);
                })
                ->selectRaw("COALESCE(desembolso_estado, 'Sin estado desembolso') as desembolso_estado")
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(COALESCE(monto_solicitado, 0)) as monto_solicitado')
                ->selectRaw('sum(COALESCE(monto_linea_credito, 0)) as monto_aprobado')
                ->selectRaw("sum(case when desembolso_aprobado_at is not null and LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado' then COALESCE(monto_linea_credito, 0) else 0 end) as monto_desembolsado")
                ->groupBy('desembolso_estado')
                ->get();
            $clienteDesembolsoEfectivos = ClientePotencial::query()
                ->where('estado_id', $cerradoId)
                ->where('efectivo', true)
                ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id))
                ->when($asesorFiltroId, fn ($q) => $q->where('asesor_id', $asesorFiltroId))
                ->where(function ($q) use ($inicioMes, $finMes) {
                    $q->whereBetween('cierre_aprobado_at', [$inicioMes, $finMes])
                        ->orWhereBetween('cierre_solicitado_at', [$inicioMes, $finMes]);
                })
                ->selectRaw("COALESCE(desembolso_estado, 'Sin estado desembolso') as desembolso_estado")
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(COALESCE(monto_solicitado, 0)) as monto_solicitado')
                ->selectRaw('sum(COALESCE(monto_linea_credito, 0)) as monto_aprobado')
                ->selectRaw("sum(case when desembolso_aprobado_at is not null and LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado' then COALESCE(monto_linea_credito, 0) else 0 end) as monto_desembolsado")
                ->groupBy('desembolso_estado')
                ->get();

            $desembolsoEfectivosResumen = $baseDesembolsoEfectivos
                ->concat($clienteDesembolsoEfectivos)
                ->groupBy('desembolso_estado')
                ->map(function ($rows, $estado) {
                    return (object) [
                        'desembolso_estado' => $estado,
                        'total' => (int) $rows->sum('total'),
                        'monto_solicitado' => (float) $rows->sum('monto_solicitado'),
                        'monto_aprobado' => (float) $rows->sum('monto_aprobado'),
                        'monto_desembolsado' => (float) $rows->sum('monto_desembolsado'),
                    ];
                })
                ->sortByDesc('total')
                ->values();
        }

        $comercialesResumen = collect();
        $acumuladoPorUsuario = collect();
        $metasResumen = collect();
        $metaPersonal = null;
        $visitasPorPersona = collect();
        $llamadasPorAsesor = collect();
        $vinculacionesPorUsuario = collect();
        $vinculacionLineasCredito = self::LINEAS_CREDITO;
        $ahorrosEfectivosPorLinea = collect();
        $creditosLineaDesembolso = collect();
        $desembolsoEstadosCredito = self::ESTADOS_DESEMBOLSO;

        $inicioMesLlamadas = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMesLlamadas = (clone $inicioMesLlamadas)->endOfMonth();
        $usuariosLlamadas = User::whereIn('role', $this->rolesGestores())
            ->when(!$esSupervisor, fn ($q) => $q->where('id', $user?->id))
            ->when($asesorFiltroId, fn ($q) => $q->where('id', $asesorFiltroId))
            ->orderBy('name')
            ->get();
        $llamadasPorAsesor = $usuariosLlamadas->map(function ($usuario) use ($inicioMesLlamadas, $finMesLlamadas) {
            $baseQuery = Gestion::query()
                ->where('asesor_id', $usuario->id)
                ->whereRaw("LOWER(TRIM(tipo)) = 'llamada'")
                ->whereBetween('created_at', [$inicioMesLlamadas, $finMesLlamadas]);

            $semana1 = (clone $baseQuery)->whereDay('created_at', '<=', 7)->count();
            $semana2 = (clone $baseQuery)->whereDay('created_at', '>=', 8)->whereDay('created_at', '<=', 14)->count();
            $semana3 = (clone $baseQuery)->whereDay('created_at', '>=', 15)->whereDay('created_at', '<=', 21)->count();
            $semana4 = (clone $baseQuery)->whereDay('created_at', '>=', 22)->count();

            return (object) [
                'name' => $usuario->name,
                'semana_1' => $semana1,
                'semana_2' => $semana2,
                'semana_3' => $semana3,
                'semana_4' => $semana4,
                'total' => $semana1 + $semana2 + $semana3 + $semana4,
            ];
        });

        if (Schema::hasTable('visitas')) {
            $usuariosVisitas = User::whereIn('role', $this->rolesGestores())
                ->when(!$esSupervisor, fn ($q) => $q->where('id', $user?->id))
                ->when($asesorFiltroId, fn ($q) => $q->where('id', $asesorFiltroId))
                ->orderBy('name')
                ->get();

            $visitasPorPersona = $usuariosVisitas->map(function ($usuario) use ($inicioMes, $finMes) {
                $visitasMes = Visita::query()
                    ->where('user_id', $usuario->id)
                    ->whereBetween('programada_at', [$inicioMes, $finMes]);
                $total = (clone $visitasMes)->count();
                $realizadas = (clone $visitasMes)->where('estado', 'realizada')->count();
                $canceladas = (clone $visitasMes)->where('estado', 'cancelada')->count();
                $pendientes = (clone $visitasMes)->where('estado', 'programada')->count();
                $registradas = (clone $visitasMes)->whereNotNull('registrada_at')->count();

                return (object) [
                    'name' => $usuario->name,
                    'total' => $total,
                    'registradas' => $registradas,
                    'realizadas' => $realizadas,
                    'canceladas' => $canceladas,
                    'pendientes' => $pendientes,
                    'porcentaje_realizadas' => $total > 0 ? round(($realizadas / $total) * 100, 1) : 0,
                ];
            });
        }

        if (Schema::hasColumns('gestions', ['es_vinculacion', 'es_ahorro', 'linea_credito_gestion'])) {
            $usuariosVinculaciones = User::whereIn('role', $this->rolesGestores())
                ->when(!$esSupervisor, fn ($q) => $q->where('id', $user?->id))
                ->when($asesorFiltroId, fn ($q) => $q->where('id', $asesorFiltroId))
                ->orderBy('name')
                ->get();

            $pendienteSupervisorId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
            $vinculacionesPorUsuario = $usuariosVinculaciones->map(function ($usuario) use ($inicioMes, $finMes, $vinculacionLineasCredito, $cerradoId, $pendienteSupervisorId) {
                $baseRows = Gestion::query()
                    ->where('asesor_id', $usuario->id)
                    ->where('es_vinculacion', true)
                    ->where('estado_id', $pendienteSupervisorId)
                    ->whereBetween('created_at', [$inicioMes, $finMes])
                    ->whereExists(function ($sub) use ($cerradoId) {
                        $sub->selectRaw('1')
                            ->from('base_asignadas')
                            ->whereColumn('base_asignadas.id', 'gestions.base_asignada_id')
                            ->where('base_asignadas.estado_id', $cerradoId)
                            ->where('base_asignadas.efectivo', true);
                    })
                    ->whereNotNull('base_asignada_id')
                    ->selectRaw("CONCAT('base-', base_asignada_id) as registro_key")
                    ->addSelect('es_ahorro', 'linea_credito_gestion')
                    ->get();

                $clienteRows = Gestion::query()
                    ->where('asesor_id', $usuario->id)
                    ->where('es_vinculacion', true)
                    ->where('estado_id', $pendienteSupervisorId)
                    ->whereBetween('created_at', [$inicioMes, $finMes])
                    ->whereExists(function ($sub) use ($cerradoId) {
                        $sub->selectRaw('1')
                            ->from('cliente_potencials')
                            ->whereColumn('cliente_potencials.id', 'gestions.cliente_potencial_id')
                            ->where('cliente_potencials.estado_id', $cerradoId)
                            ->where('cliente_potencials.efectivo', true);
                    })
                    ->whereNotNull('cliente_potencial_id')
                    ->selectRaw("CONCAT('cliente-', cliente_potencial_id) as registro_key")
                    ->addSelect('es_ahorro', 'linea_credito_gestion')
                    ->get();

                $vinculaciones = $baseRows
                    ->concat($clienteRows)
                    ->unique('registro_key')
                    ->values();

                $porLinea = [];
                foreach ($vinculacionLineasCredito as $linea) {
                    $porLinea[$linea] = (int) $vinculaciones
                        ->where('es_ahorro', false)
                        ->where('linea_credito_gestion', $linea)
                        ->count();
                }

                return (object) [
                    'name' => $usuario->name,
                    'total' => (int) $vinculaciones->count(),
                    'ahorros' => (int) $vinculaciones->where('es_ahorro', true)->count(),
                    'creditos' => (int) $vinculaciones->where('es_ahorro', false)->count(),
                    'creditos_sin_linea' => (int) $vinculaciones
                        ->where('es_ahorro', false)
                        ->filter(fn ($row) => empty($row->linea_credito_gestion))
                        ->count(),
                    'por_linea' => $porLinea,
                ];
            });

            if (Schema::hasColumns('gestions', ['linea_ahorro', 'monto_ahorro'])) {
                $ahorrosBase = Gestion::query()
                    ->where('gestions.es_ahorro', true)
                    ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
                    ->whereNotNull('gestions.base_asignada_id')
                    ->when(!$esSupervisor, fn ($q) => $q->where('gestions.asesor_id', $user?->id))
                    ->when($asesorFiltroId, fn ($q) => $q->where('gestions.asesor_id', $asesorFiltroId))
                    ->whereExists(function ($sub) use ($cerradoId) {
                        $sub->selectRaw('1')
                            ->from('base_asignadas')
                            ->whereColumn('base_asignadas.id', 'gestions.base_asignada_id')
                            ->where('base_asignadas.estado_id', $cerradoId)
                            ->where('base_asignadas.efectivo', true);
                    })
                    ->get(['base_asignada_id', 'linea_ahorro', 'monto_ahorro'])
                    ->map(function ($row) {
                        $row->registro_key = 'base-' . $row->base_asignada_id;
                        $row->linea_ahorro = trim((string) $row->linea_ahorro) ?: 'Sin linea';
                        return $row;
                    });

                $ahorrosClientes = Gestion::query()
                    ->where('gestions.es_ahorro', true)
                    ->whereBetween('gestions.created_at', [$inicioMes, $finMes])
                    ->whereNotNull('gestions.cliente_potencial_id')
                    ->when(!$esSupervisor, fn ($q) => $q->where('gestions.asesor_id', $user?->id))
                    ->when($asesorFiltroId, fn ($q) => $q->where('gestions.asesor_id', $asesorFiltroId))
                    ->whereExists(function ($sub) use ($cerradoId) {
                        $sub->selectRaw('1')
                            ->from('cliente_potencials')
                            ->whereColumn('cliente_potencials.id', 'gestions.cliente_potencial_id')
                            ->where('cliente_potencials.estado_id', $cerradoId)
                            ->where('cliente_potencials.efectivo', true);
                    })
                    ->get(['cliente_potencial_id', 'linea_ahorro', 'monto_ahorro'])
                    ->map(function ($row) {
                        $row->registro_key = 'cliente-' . $row->cliente_potencial_id;
                        $row->linea_ahorro = trim((string) $row->linea_ahorro) ?: 'Sin linea';
                        return $row;
                    });

                $ahorrosEfectivosPorLinea = $ahorrosBase
                    ->concat($ahorrosClientes)
                    ->unique('registro_key')
                    ->groupBy('linea_ahorro')
                    ->map(function ($rows, $linea) {
                        return (object) [
                            'linea_ahorro' => $linea,
                            'total' => (int) $rows->count(),
                            'monto' => (float) $rows->sum('monto_ahorro'),
                        ];
                    })
                    ->sortByDesc('monto')
                    ->values();
            }

            if (Schema::hasColumn('base_asignadas', 'desembolso_estado') && Schema::hasColumn('cliente_potencials', 'desembolso_estado')) {
                $periodoCredito = function ($q, string $tabla) use ($inicioMes, $finMes) {
                    $q->whereBetween("{$tabla}.cierre_solicitado_at", [$inicioMes, $finMes])
                        ->orWhereBetween("{$tabla}.cierre_aprobado_at", [$inicioMes, $finMes])
                        ->orWhere(function ($sub) use ($tabla, $inicioMes, $finMes) {
                            $sub->whereNull("{$tabla}.cierre_solicitado_at")
                                ->whereNull("{$tabla}.cierre_aprobado_at")
                                ->whereBetween("{$tabla}.updated_at", [$inicioMes, $finMes]);
                        });
                };

                $creditosBaseDesembolsoDetalle = BaseAsignada::query()
                    ->whereNotNull('linea_credito')
                    ->where('linea_credito', '<>', '')
                    ->where(function ($q) use ($periodoCredito) {
                        $periodoCredito($q, 'base_asignadas');
                    })
                    ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id))
                    ->when($asesorFiltroId, fn ($q) => $q->where('asesor_id', $asesorFiltroId))
                    ->selectRaw("COALESCE(NULLIF(linea_credito, ''), 'Sin linea') as linea_credito")
                    ->selectRaw("COALESCE(NULLIF(desembolso_estado, ''), 'Sin estado desembolso') as desembolso_estado");

                $creditosBaseDesembolso = DB::query()
                    ->fromSub($creditosBaseDesembolsoDetalle, 'creditos_base_desembolso')
                    ->selectRaw('count(*) as total')
                    ->selectRaw('linea_credito')
                    ->selectRaw('desembolso_estado')
                    ->groupBy('linea_credito', 'desembolso_estado')
                    ->get();

                $creditosClientesDesembolsoDetalle = ClientePotencial::query()
                    ->whereNotNull('linea_credito')
                    ->where('linea_credito', '<>', '')
                    ->where(function ($q) use ($periodoCredito) {
                        $periodoCredito($q, 'cliente_potencials');
                    })
                    ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id))
                    ->when($asesorFiltroId, fn ($q) => $q->where('asesor_id', $asesorFiltroId))
                    ->selectRaw("COALESCE(NULLIF(linea_credito, ''), 'Sin linea') as linea_credito")
                    ->selectRaw("COALESCE(NULLIF(desembolso_estado, ''), 'Sin estado desembolso') as desembolso_estado");

                $creditosClientesDesembolso = DB::query()
                    ->fromSub($creditosClientesDesembolsoDetalle, 'creditos_clientes_desembolso')
                    ->selectRaw('count(*) as total')
                    ->selectRaw('linea_credito')
                    ->selectRaw('desembolso_estado')
                    ->groupBy('linea_credito', 'desembolso_estado')
                    ->get();

                $creditosLineaDesembolso = $creditosBaseDesembolso
                    ->concat($creditosClientesDesembolso)
                    ->groupBy('linea_credito')
                    ->map(function ($rows, $linea) use ($desembolsoEstadosCredito) {
                        $porEstado = [];
                        foreach ($desembolsoEstadosCredito as $estadoDesembolso) {
                            $porEstado[$estadoDesembolso] = (int) $rows
                                ->where('desembolso_estado', $estadoDesembolso)
                                ->sum('total');
                        }
                        $sinEstado = (int) $rows
                            ->where('desembolso_estado', 'Sin estado desembolso')
                            ->sum('total');

                        return (object) [
                            'linea_credito' => $linea,
                            'total' => (int) $rows->sum('total'),
                            'por_estado' => $porEstado,
                            'sin_estado' => $sinEstado,
                        ];
                    })
                    ->sortByDesc('total')
                    ->values();
            }
        }

        if ($esSupervisor) {
            $comercialesResumen = User::whereIn('role', $this->rolesGestores())
                ->when($asesorFiltroId, fn ($q) => $q->where('id', $asesorFiltroId))
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
                    $montoDesembolsadoMes = BaseAsignada::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where('efectivo', true)
                        ->whereNotNull('cierre_aprobado_at')
                        ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                        ->whereBetween('desembolso_aprobado_at', [$inicioMes, $finMes])
                        ->sum('monto_linea_credito');
                    $desembolsosMes = BaseAsignada::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where('efectivo', true)
                        ->whereNotNull('cierre_aprobado_at')
                        ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                        ->whereBetween('desembolso_aprobado_at', [$inicioMes, $finMes])
                        ->count();
                    $montoSolicitadoMes = BaseAsignada::query()
                        ->where('asesor_id', $comercial->id)
                        ->whereBetween('created_at', [$inicioMes, $finMes])
                        ->sum('monto_solicitado');
                    $porcentajeCierreVsAsignados = $asignadosMes > 0 ? round(($cierresMes / $asignadosMes) * 100, 1) : 0;
                    $tiempoInvertidoMinMes = (int) Gestion::query()
                        ->where('asesor_id', $comercial->id)
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
                    $comercial->monto_desembolsado_mes = $montoDesembolsadoMes;
                    $comercial->desembolsos_mes = $desembolsosMes;
                    $comercial->monto_solicitado_mes = $montoSolicitadoMes;
                    $comercial->total_llamadas_mes = (int) Gestion::query()
                        ->where('asesor_id', $comercial->id)
                        ->whereRaw("LOWER(TRIM(tipo)) = 'llamada'")
                        ->whereBetween('created_at', [$inicioMes, $finMes])
                        ->count();
                    $resumenAhorros = $this->resumenAhorrosUsuario((int) $comercial->id, $inicioMes, $finMes, $cerradoId);
                    $comercial->ahorros_mes = $resumenAhorros['cantidad'];
                    $comercial->monto_ahorros_mes = $resumenAhorros['monto'];
                    $comercial->tiempo_invertido_min_mes = $tiempoInvertidoMinMes;
                    $comercial->prom_tiempo_cierre_min = $promTiempoCierreMin;
                    $conteosDesembolso = $this->conteosDesembolsoUsuario((int) $comercial->id, $inicioMes, $finMes, $cerradoId);
                    $comercial->desembolso_desembolsado_mes = $conteosDesembolso['desembolsado'];
                    $comercial->desembolso_por_desembolsar_mes = $conteosDesembolso['por_desembolsar'];
                    $comercial->desembolso_aplazado_mes = $conteosDesembolso['aplazado'];
                    $comercial->desembolso_negado_mes = $conteosDesembolso['negado'];
                    $comercial->desembolso_desistido_mes = $conteosDesembolso['desistido'];
                    $comercial->monto_desembolso_desembolsado_mes = $conteosDesembolso['monto_desembolsado'];
                    $comercial->monto_desembolso_por_desembolsar_mes = $conteosDesembolso['monto_por_desembolsar'];
                    $comercial->monto_desembolso_aplazado_mes = $conteosDesembolso['monto_aplazado'];
                    $comercial->monto_desembolso_negado_mes = $conteosDesembolso['monto_negado'];
                    $comercial->monto_desembolso_desistido_mes = $conteosDesembolso['monto_desistido'];
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

                    $cpMes = ClientePotencial::query()
                        ->where('asesor_id', $comercial->id)
                        ->whereBetween('created_at', [$inicioMes, $finMes]);
                    $comercial->asignados_mes += (clone $cpMes)->count();
                    $comercial->gestionados_registros += (int) ClientePotencial::query()
                        ->where('asesor_id', $comercial->id)
                        ->whereHas('gestiones', function ($g) use ($inicioMes, $finMes) {
                            $g->whereBetween('created_at', [$inicioMes, $finMes]);
                        })->count();
                    $comercial->pendientes_registros += (int) ClientePotencial::query()
                        ->where('asesor_id', $comercial->id)
                        ->whereBetween('created_at', [$inicioMes, $finMes])
                        ->where('estado_id', Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id'))
                        ->count();
                    $cierresCpMesQ = ClientePotencial::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where(function ($q) use ($inicioMes, $finMes, $cerradoId) {
                            if (Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at')) {
                                $q->whereBetween('cierre_solicitado_at', [$inicioMes, $finMes]);
                            }
                            $q->orWhereExists(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                                $sub->selectRaw('1')
                                    ->from('gestions')
                                    ->whereColumn('gestions.cliente_potencial_id', 'cliente_potencials.id')
                                    ->where('gestions.estado_id', $cerradoId)
                                    ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                            });
                        });
                    $cpCierres = (clone $cierresCpMesQ)->count();
                    $comercial->cierres_mes += $cpCierres;
                    $comercial->monto_colocado_mes += (float) (clone $cierresCpMesQ)->sum('monto_linea_credito');
                    $comercial->monto_desembolsado_mes += (float) ClientePotencial::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where('efectivo', true)
                        ->whereNotNull('cierre_aprobado_at')
                        ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                        ->whereBetween('desembolso_aprobado_at', [$inicioMes, $finMes])
                        ->sum('monto_linea_credito');
                    $comercial->desembolsos_mes += (int) ClientePotencial::query()
                        ->where('asesor_id', $comercial->id)
                        ->where('estado_id', $cerradoId)
                        ->where('efectivo', true)
                        ->whereNotNull('cierre_aprobado_at')
                        ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                        ->whereBetween('desembolso_aprobado_at', [$inicioMes, $finMes])
                        ->count();
                    $comercial->monto_solicitado_mes += (float) (clone $cpMes)->sum('monto_solicitado');
                    if (Schema::hasColumn('cliente_potencials', 'efectivo')) {
                        $comercial->efectivo_si_mes += (int) (clone $cierresCpMesQ)->where('efectivo', true)->count();
                        $comercial->efectivo_no_mes += (int) (clone $cierresCpMesQ)->where('efectivo', false)->count();
                    }
                    $comercial->porcentaje_cierre_vs_asignados = $comercial->asignados_mes > 0 ? round(($comercial->cierres_mes / $comercial->asignados_mes) * 100, 1) : 0;
                    return $comercial;
                });

            $acumuladoPorUsuario = User::whereIn('role', $this->rolesGestores())
                ->when($asesorFiltroId, fn ($q) => $q->where('id', $asesorFiltroId))
                ->orderBy('name')
                ->get()
                ->map(function ($usuario) use ($cerradoId, $pendienteId) {
                    $baseUsuario = BaseAsignada::query()->where('asesor_id', $usuario->id);
                    $cpUsuario = ClientePotencial::query()->where('asesor_id', $usuario->id);
                    $total = (clone $baseUsuario)->count() + (clone $cpUsuario)->count();
                    $gestionados = (clone $baseUsuario)->whereHas('gestiones')->count() + (clone $cpUsuario)->whereHas('gestiones')->count();
                    $cerrados = $cerradoId ? ((clone $baseUsuario)->where('estado_id', $cerradoId)->count() + (clone $cpUsuario)->where('estado_id', $cerradoId)->count()) : 0;
                    $pendientes = $pendienteId ? ((clone $baseUsuario)->where('estado_id', $pendienteId)->count() + (clone $cpUsuario)->where('estado_id', $pendienteId)->count()) : 0;
                    $efectivoSi = $cerradoId ? ((clone $baseUsuario)->where('estado_id', $cerradoId)->where('efectivo', true)->count() + (Schema::hasColumn('cliente_potencials', 'efectivo') ? (clone $cpUsuario)->where('estado_id', $cerradoId)->where('efectivo', true)->count() : 0)) : 0;
                    $efectivoNo = $cerradoId ? ((clone $baseUsuario)->where('estado_id', $cerradoId)->where('efectivo', false)->count() + (Schema::hasColumn('cliente_potencials', 'efectivo') ? (clone $cpUsuario)->where('estado_id', $cerradoId)->where('efectivo', false)->count() : 0)) : 0;
                    $montoSolicitado = (clone $baseUsuario)->sum('monto_solicitado') + (clone $cpUsuario)->sum('monto_solicitado');
                    $montoAprobado = $cerradoId ? ((clone $baseUsuario)->where('estado_id', $cerradoId)->sum('monto_linea_credito') + (clone $cpUsuario)->where('estado_id', $cerradoId)->sum('monto_linea_credito')) : 0;
                    $montoDesembolsadoUsuario = $cerradoId
                        ? ((clone $baseUsuario)
                            ->where('estado_id', $cerradoId)
                            ->where('efectivo', true)
                            ->whereNotNull('cierre_aprobado_at')
                            ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                            ->whereNotNull('desembolso_aprobado_at')
                            ->sum('monto_linea_credito')
                            + (clone $cpUsuario)
                                ->where('estado_id', $cerradoId)
                                ->where('efectivo', true)
                                ->whereNotNull('cierre_aprobado_at')
                                ->whereRaw("LOWER(TRIM(COALESCE(desembolso_estado, ''))) = 'desembolsado'")
                                ->whereNotNull('desembolso_aprobado_at')
                                ->sum('monto_linea_credito'))
                        : 0;

                    $usuario->ac_total = $total;
                    $usuario->ac_gestionados = $gestionados;
                    $usuario->ac_cerrados = $cerrados;
                    $usuario->ac_pendientes = $pendientes;
                    $usuario->ac_efectivo_si = $efectivoSi;
                    $usuario->ac_efectivo_no = $efectivoNo;
                    $usuario->ac_monto_solicitado = $montoSolicitado;
                    $usuario->ac_monto_aprobado = $montoAprobado;
                    $usuario->ac_monto_desembolsado = $montoDesembolsadoUsuario;
                    $usuario->ac_porcentaje_gestion = $total > 0 ? round(($gestionados / $total) * 100, 1) : 0;
                    $usuario->ac_porcentaje_cierre = $total > 0 ? round(($cerrados / $total) * 100, 1) : 0;
                    return $usuario;
                });

            if (Schema::hasTable('metas_comerciales')) {
                $metasMes = MetaComercial::query()
                    ->where('anio', $anio)
                    ->when($periodo === 'mes', fn ($q) => $q->where('mes', $mes))
                    ->selectRaw('user_id, sum(monto_meta) as monto_meta')
                    ->groupBy('user_id')
                    ->get()
                    ->keyBy('user_id');
                $metasResumen = $comercialesResumen->map(function ($usuario) use ($metasMes) {
                    $meta = $metasMes->get($usuario->id);
                    $montoMeta = (float) ($meta?->monto_meta ?? 0);
                    $montoDesembolsadoMeta = (float) ($usuario->monto_desembolsado_mes ?? 0);
                    $restante = max(0, $montoMeta - $montoDesembolsadoMeta);
                    $cumplimiento = $montoMeta > 0 ? round(($montoDesembolsadoMeta / $montoMeta) * 100, 1) : 0;
                    return (object) [
                        'user_id' => $usuario->id,
                        'name' => $usuario->name,
                        'monto_colocado' => $montoDesembolsadoMeta,
                        'monto_desembolsado' => $montoDesembolsadoMeta,
                        'monto_meta' => $montoMeta,
                        'restante' => $restante,
                        'cumplimiento' => $cumplimiento,
                        'cumple' => $montoMeta > 0 && $montoDesembolsadoMeta >= $montoMeta,
                    ];
                });
            }
        } else {
            if ($user) {
                $comercialesResumen = collect([
                    $this->resumenRendimientoUsuario($user, $inicioMes, $finMes, $cerradoId),
                ]);
            }

            if ($user && Schema::hasTable('metas_comerciales')) {
                $meta = MetaComercial::query()
                    ->where('user_id', $user->id)
                    ->where('anio', $anio)
                    ->when($periodo === 'mes', fn ($q) => $q->where('mes', $mes))
                    ->selectRaw('sum(monto_meta) as monto_meta')
                    ->first();
                $montoMeta = (float) ($meta?->monto_meta ?? 0);
                $montoDesembolsadoMeta = (float) ($comercialesResumen->first()?->monto_desembolsado_mes ?? 0);
                $restante = max(0, $montoMeta - $montoDesembolsadoMeta);
                $cumplimiento = $montoMeta > 0 ? round(($montoDesembolsadoMeta / $montoMeta) * 100, 1) : 0;
                $metaPersonal = (object) [
                    'monto_meta' => $montoMeta,
                    'monto_colocado' => $montoDesembolsadoMeta,
                    'monto_desembolsado' => $montoDesembolsadoMeta,
                    'restante' => $restante,
                    'cumplimiento' => $cumplimiento,
                    'cumple' => $montoMeta > 0 && $montoDesembolsadoMeta >= $montoMeta,
                ];
            }
        }

        return view('dashboard.index', compact(
            'esSupervisor',
            'asesoresFiltro',
            'asesorFiltroId',
            'totalBases',
            'gestionadas',
            'pendientesAprobacion',
            'cerradas',
            'cerradasNoEfectivas',
            'devueltas',
            'montoCerrado',
            'montoDesembolsado',
            'porcentajeGestion',
            'porcentajeCierre',
            'estadosResumen',
            'desembolsoResumen',
            'desembolsoEfectivosResumen',
            'comercialesResumen',
            'acumuladoPorUsuario',
            'metasResumen',
            'metaPersonal',
            'visitasPorPersona',
            'llamadasPorAsesor',
            'vinculacionesPorUsuario',
            'vinculacionLineasCredito',
            'ahorrosEfectivosPorLinea',
            'creditosLineaDesembolso',
            'desembolsoEstadosCredito',
            
            'mes',
            'anio',
            'periodo',
            'periodoTexto',
            'periodoActualTitulo',
            'periodoAnteriorTitulo',
            'inicioMes',
            'kpiMesActual',
            'kpiMesAnterior',
            'canalesMes',
            'cierresCanalMes',
            'tiempoInvertidoMesMin',
            'promTiempoCierreMesMin',
            'efectivoSiMes',
            'efectivoNoMes',
            'promPrimeraGestionMesMin',
            'promAprobacionSupervisorMesMin'
        ));
    }

    public function dashboardReporte(Request $request)
    {
        $user = auth()->user();
        $esSupervisor = $this->isSupervisor();
        $mes = max(1, min(12, (int) $request->input('mes', now()->month)));
        $anio = (int) $request->input('anio', now()->year);
        $periodo = in_array($request->input('periodo'), ['mes', 'anio'], true)
            ? (string) $request->input('periodo')
            : 'mes';
        if ($anio < 2026 || $anio > 2036) {
            $anio = max(2026, min(2036, (int) now()->year));
        }
        if ($periodo === 'anio') {
            $inicio = Carbon::create($anio, 1, 1)->startOfYear();
            $fin = (clone $inicio)->endOfYear();
            $periodoTitulo = (string) $anio;
        } else {
            $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
            $fin = (clone $inicio)->endOfMonth();
            $periodoTitulo = $inicio->locale('es')->translatedFormat('F Y');
        }

        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $filename = 'reporte_dashboard_' . ($periodo === 'anio' ? $anio : sprintf('%04d_%02d', $anio, $mes)) . '.csv';

        $baseScope = BaseAsignada::query()
            ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id));
        $clienteScope = ClientePotencial::query()
            ->when(!$esSupervisor, fn ($q) => $q->where('asesor_id', $user?->id));

        $baseIds = (clone $baseScope)->select('id');
        $clienteIds = (clone $clienteScope)->select('id');

        $registrosCargados = (clone $baseScope)->whereBetween('created_at', [$inicio, $fin])->count()
            + (clone $clienteScope)->whereBetween('created_at', [$inicio, $fin])->count();
        $gestionados = Gestion::query()
            ->whereBetween('created_at', [$inicio, $fin])
            ->where(function ($q) use ($baseIds, $clienteIds) {
                $q->whereIn('base_asignada_id', $baseIds)
                    ->orWhereIn('cliente_potencial_id', $clienteIds);
            })
            ->selectRaw('count(distinct base_asignada_id) as bases')
            ->selectRaw('count(distinct cliente_potencial_id) as clientes')
            ->first();
        $totalGestionados = (int) ($gestionados?->bases ?? 0) + (int) ($gestionados?->clientes ?? 0);

        $periodoRegistro = function ($q, string $tabla) use ($inicio, $fin, $cerradoId) {
            $q->whereBetween("{$tabla}.created_at", [$inicio, $fin])
                ->orWhereBetween("{$tabla}.updated_at", [$inicio, $fin])
                ->orWhereBetween("{$tabla}.cierre_solicitado_at", [$inicio, $fin])
                ->orWhereBetween("{$tabla}.cierre_aprobado_at", [$inicio, $fin])
                ->orWhereExists(function ($sub) use ($tabla, $inicio, $fin) {
                    $key = $tabla === 'base_asignadas' ? 'base_asignada_id' : 'cliente_potencial_id';
                    $sub->selectRaw('1')
                        ->from('gestions')
                        ->whereColumn("gestions.{$key}", "{$tabla}.id")
                        ->whereBetween('gestions.created_at', [$inicio, $fin]);
                });
        };

        $basesReporte = (clone $baseScope)
            ->with(['estado', 'asesor'])
            ->where(function ($q) use ($periodoRegistro) {
                $periodoRegistro($q, 'base_asignadas');
            })
            ->orderBy('lote_nombre')
            ->orderBy('nombre')
            ->get();

        $clientesReporte = (clone $clienteScope)
            ->with(['estado', 'asesor'])
            ->where(function ($q) use ($periodoRegistro) {
                $periodoRegistro($q, 'cliente_potencials');
            })
            ->orderBy('lote_nombre')
            ->orderBy('nombre')
            ->get();

        $cierres = $basesReporte->where('estado_id', $cerradoId)->count() + $clientesReporte->where('estado_id', $cerradoId)->count();
        $pendientes = $pendienteId
            ? ($basesReporte->where('estado_id', $pendienteId)->count() + $clientesReporte->where('estado_id', $pendienteId)->count())
            : 0;
        $montoSolicitado = (float) $basesReporte->sum('monto_solicitado') + (float) $clientesReporte->sum('monto_solicitado');
        $montoAprobado = (float) $basesReporte->where('estado_id', $cerradoId)->sum('monto_linea_credito')
            + (float) $clientesReporte->where('estado_id', $cerradoId)->sum('monto_linea_credito');

        $gestionesReporte = Gestion::query()
            ->with(['estado', 'asesor', 'baseAsignada.asesor', 'clientePotencial.asesor'])
            ->whereBetween('created_at', [$inicio, $fin])
            ->where(function ($q) use ($baseIds, $clienteIds) {
                $q->whereIn('base_asignada_id', $baseIds)
                    ->orWhereIn('cliente_potencial_id', $clienteIds);
            })
            ->latest('created_at')
            ->get();

        $creditosLineaDesembolso = collect([
            ...$basesReporte->filter(fn ($r) => filled($r->linea_credito))->map(fn ($r) => [
                'linea_credito' => $r->linea_credito ?: 'Sin linea',
                'desembolso_estado' => $r->desembolso_estado ?: 'Sin estado desembolso',
            ])->all(),
            ...$clientesReporte->filter(fn ($r) => filled($r->linea_credito))->map(fn ($r) => [
                'linea_credito' => $r->linea_credito ?: 'Sin linea',
                'desembolso_estado' => $r->desembolso_estado ?: 'Sin estado desembolso',
            ])->all(),
        ])
            ->groupBy(fn ($row) => $row['linea_credito'] . '|' . $row['desembolso_estado'])
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'linea_credito' => $first['linea_credito'],
                    'desembolso_estado' => $first['desembolso_estado'],
                    'total' => $rows->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return response()->streamDownload(function () use (
            $periodoTitulo,
            $periodo,
            $inicio,
            $fin,
            $registrosCargados,
            $totalGestionados,
            $cierres,
            $pendientes,
            $montoSolicitado,
            $montoAprobado,
            $basesReporte,
            $clientesReporte,
            $gestionesReporte,
            $creditosLineaDesembolso
        ) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $put = fn (array $row) => fputcsv($out, $row, ';');
            $money = fn ($value) => number_format((float) $value, 0, ',', '.');
            $date = fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d H:i') : 'N/A';
            $bool = fn ($value) => is_null($value) ? 'N/A' : ($value ? 'SI' : 'NO');

            $put(['Reporte dashboard', $periodoTitulo]);
            $put(['Tipo periodo', $periodo === 'anio' ? 'Anual' : 'Mensual']);
            $put(['Desde', $inicio->format('Y-m-d H:i')]);
            $put(['Hasta', $fin->format('Y-m-d H:i')]);
            $put([]);

            $put(['Resumen']);
            $put(['Indicador', 'Valor']);
            $put(['Registros cargados', $registrosCargados]);
            $put(['Registros gestionados', $totalGestionados]);
            $put(['Cierres', $cierres]);
            $put(['Pendientes aprobacion', $pendientes]);
            $put(['Monto solicitado', $money($montoSolicitado)]);
            $put(['Monto aprobado', $money($montoAprobado)]);
            $put([]);

            $put(['Base asignada']);
            $put(['Lote', 'Nombre', 'Cedula', 'Linea credito', 'Empresa', 'Telefono', 'Origen', 'Estado', 'Comercial', 'Efectivo', 'Monto solicitado', 'Monto aprobado', 'Estado desembolso', 'Creado', 'Ultima gestion']);
            foreach ($basesReporte as $base) {
                $put([
                    $base->lote_nombre ?? 'SIN LOTE',
                    $base->nombre,
                    $base->cedula,
                    $base->linea_credito,
                    $base->empresa,
                    $base->telefono,
                    $base->origen,
                    $base->estado?->nombre ?? 'Sin estado',
                    $base->asesor?->name ?? 'Sin asignar',
                    $bool($base->efectivo),
                    $money($base->monto_solicitado),
                    $money($base->monto_linea_credito),
                    $base->desembolso_estado ?: 'N/A',
                    $date($base->created_at),
                    $date($base->ultima_gestion_at),
                ]);
            }
            $put([]);

            $put(['Clientes potenciales']);
            $put(['Lote', 'Nombre', 'Cedula', 'Linea credito', 'Empresa', 'Telefono', 'Origen', 'Estado', 'Comercial', 'Efectivo', 'Monto solicitado', 'Monto aprobado', 'Estado desembolso', 'Creado', 'Ultima gestion']);
            foreach ($clientesReporte as $cliente) {
                $put([
                    $cliente->lote_nombre ?? 'CLIENTE POTENCIAL',
                    $cliente->nombre,
                    $cliente->cedula,
                    $cliente->linea_credito,
                    $cliente->empresa,
                    $cliente->telefono,
                    $cliente->fuente,
                    $cliente->estado?->nombre ?? 'Sin estado',
                    $cliente->asesor?->name ?? 'Sin asignar',
                    $bool($cliente->efectivo),
                    $money($cliente->monto_solicitado),
                    $money($cliente->monto_linea_credito),
                    $cliente->desembolso_estado ?: 'N/A',
                    $date($cliente->created_at),
                    $date($cliente->ultima_gestion_at),
                ]);
            }
            $put([]);

            $put(['Gestiones y vinculaciones']);
            $put(['Fecha', 'Tipo registro', 'Lote', 'Cliente', 'Cedula', 'Asesor gestion', 'Canal gestion', 'Estado gestion', 'Vinculacion', 'Ahorro', 'Linea credito gestion', 'Tiempo min', 'Detalle']);
            foreach ($gestionesReporte as $gestion) {
                $registro = $gestion->baseAsignada ?: $gestion->clientePotencial;
                $put([
                    $date($gestion->created_at),
                    $gestion->base_asignada_id ? 'Base asignada' : 'Cliente potencial',
                    $registro?->lote_nombre ?? ($gestion->base_asignada_id ? 'SIN LOTE' : 'CLIENTE POTENCIAL'),
                    $registro?->nombre,
                    $registro?->cedula,
                    $gestion->asesor?->name ?? 'N/A',
                    $gestion->tipo,
                    $gestion->estado?->nombre ?? 'Sin estado',
                    $gestion->es_vinculacion ? 'SI' : 'NO',
                    $gestion->es_ahorro ? 'SI' : 'NO',
                    $gestion->linea_credito_gestion ?: 'N/A',
                    $gestion->minutos_invertidos ?: 'N/A',
                    $gestion->detalle,
                ]);
            }
            $put([]);

            $put(['Creditos por linea y estado desembolso']);
            $put(['Linea credito', 'Estado desembolso', 'Total']);
            foreach ($creditosLineaDesembolso as $row) {
                $put([$row['linea_credito'], $row['desembolso_estado'], $row['total']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function guardarMetaMensual(Request $request)
    {
        $this->forbidIfNotSupervisor();
        abort_unless(Schema::hasTable('metas_comerciales'), 400, 'No existe la tabla de metas_comerciales.');
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'anio' => ['required', 'integer', 'min:2026', 'max:2036'],
            'monto_meta' => ['required', 'regex:/^[0-9]+$/'],
        ]);

        MetaComercial::updateOrCreate(
            [
                'user_id' => (int) $data['user_id'],
                'mes' => (int) $data['mes'],
                'anio' => (int) $data['anio'],
            ],
            [
                'monto_meta' => (float) $data['monto_meta'],
            ]
        );

        return redirect()->route('dashboard', ['mes' => $data['mes'], 'anio' => $data['anio']])->with('ok', 'Meta mensual guardada.');
    }

    public function dashboardDetalle(Request $request)
    {
        $user = auth()->user();
        $esSupervisor = $this->isSupervisor();
        $tipo = (string) $request->input('tipo', 'cargados');
        $mes = max(1, min(12, (int) $request->input('mes', now()->month)));
        $anio = (int) $request->input('anio', now()->year);
        $periodo = in_array($request->input('periodo'), ['mes', 'anio'], true)
            ? (string) $request->input('periodo')
            : 'mes';
        if ($anio < 2026 || $anio > 2036) {
            $anio = max(2026, min(2036, (int) now()->year));
        }
        if ($periodo === 'anio') {
            $inicioMes = Carbon::create($anio, 1, 1)->startOfYear();
            $finMes = (clone $inicioMes)->endOfYear();
        } else {
            $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
            $finMes = (clone $inicioMes)->endOfMonth();
        }
        $periodoActualTitulo = $periodo === 'anio'
            ? (string) $anio
            : $inicioMes->locale('es')->translatedFormat('F Y');
        $asesorFiltroId = null;
        if ($esSupervisor && $request->filled('asesor_id')) {
            $asesorIdRequest = (int) $request->input('asesor_id');
            $asesorExiste = User::whereIn('role', $this->rolesGestores())->where('id', $asesorIdRequest)->exists();
            if ($asesorExiste) {
                $asesorFiltroId = $asesorIdRequest;
            }
        }
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $devueltaId = Estado::where('slug', 'devuelta')->value('id');

        $baseQ = DB::table('base_asignadas')
            ->leftJoin('estados', 'estados.id', '=', 'base_asignadas.estado_id')
            ->leftJoin('users', 'users.id', '=', 'base_asignadas.asesor_id')
            ->selectRaw("'base' as origen_registro")
            ->selectRaw('base_asignadas.id as registro_id')
            ->selectRaw('base_asignadas.lote_nombre as lote')
            ->selectRaw('base_asignadas.nombre as cliente')
            ->selectRaw('base_asignadas.cedula as cedula')
            ->selectRaw("COALESCE(estados.nombre, 'N/A') as estado")
            ->selectRaw("COALESCE(users.name, 'N/A') as asesor")
            ->selectRaw('base_asignadas.monto_solicitado as monto_solicitado')
            ->selectRaw('base_asignadas.monto_linea_credito as monto_aprobado')
            ->selectRaw('base_asignadas.created_at as fecha_ref');
        if (!$esSupervisor) {
            $baseQ->where('base_asignadas.asesor_id', $user?->id);
        } elseif ($asesorFiltroId) {
            $baseQ->where('base_asignadas.asesor_id', $asesorFiltroId);
        }

        $clienteQ = DB::table('cliente_potencials')
            ->leftJoin('estados', 'estados.id', '=', 'cliente_potencials.estado_id')
            ->leftJoin('users', 'users.id', '=', 'cliente_potencials.asesor_id')
            ->selectRaw("'cliente_potencial' as origen_registro")
            ->selectRaw('cliente_potencials.id as registro_id')
            ->selectRaw("COALESCE(cliente_potencials.lote_nombre, 'CLIENTE POTENCIAL') as lote")
            ->selectRaw('cliente_potencials.nombre as cliente')
            ->selectRaw('cliente_potencials.cedula as cedula')
            ->selectRaw("COALESCE(estados.nombre, 'N/A') as estado")
            ->selectRaw("COALESCE(users.name, 'N/A') as asesor")
            ->selectRaw('cliente_potencials.monto_solicitado as monto_solicitado')
            ->selectRaw('cliente_potencials.monto_linea_credito as monto_aprobado')
            ->selectRaw('cliente_potencials.created_at as fecha_ref');
        if (!$esSupervisor) {
            $clienteQ->where('cliente_potencials.asesor_id', $user?->id);
        } elseif ($asesorFiltroId) {
            $clienteQ->where('cliente_potencials.asesor_id', $asesorFiltroId);
        }

        if ($tipo === 'cargados') {
            $baseQ->whereBetween('base_asignadas.created_at', [$inicioMes, $finMes]);
            $clienteQ->whereBetween('cliente_potencials.created_at', [$inicioMes, $finMes]);
        } elseif ($tipo === 'gestionados') {
            $baseQ->whereExists(function ($g) use ($inicioMes, $finMes) {
                $g->selectRaw('1')->from('gestions')
                    ->whereColumn('gestions.base_asignada_id', 'base_asignadas.id')
                    ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
            });
            $clienteQ->whereExists(function ($g) use ($inicioMes, $finMes) {
                $g->selectRaw('1')->from('gestions')
                    ->whereColumn('gestions.cliente_potencial_id', 'cliente_potencials.id')
                    ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
            });
        } elseif ($tipo === 'cerrados') {
            $baseQ->where('base_asignadas.estado_id', $cerradoId)
                ->where(function ($q) use ($inicioMes, $finMes, $cerradoId) {
                    $q->whereBetween('base_asignadas.cierre_solicitado_at', [$inicioMes, $finMes])
                        ->orWhereExists(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                            $sub->selectRaw('1')
                                ->from('gestions')
                                ->whereColumn('gestions.base_asignada_id', 'base_asignadas.id')
                                ->where('gestions.estado_id', $cerradoId)
                                ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                        });
                });
            $clienteQ->where('cliente_potencials.estado_id', $cerradoId)
                ->where(function ($q) use ($inicioMes, $finMes, $cerradoId) {
                    if (Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at')) {
                        $q->whereBetween('cliente_potencials.cierre_solicitado_at', [$inicioMes, $finMes]);
                    }
                    $q->orWhereExists(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                        $sub->selectRaw('1')
                            ->from('gestions')
                            ->whereColumn('gestions.cliente_potencial_id', 'cliente_potencials.id')
                            ->where('gestions.estado_id', $cerradoId)
                            ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                    });
                });
        } elseif ($tipo === 'pendientes') {
            $baseQ->where('base_asignadas.estado_id', $pendienteId)->whereBetween('base_asignadas.created_at', [$inicioMes, $finMes]);
            $clienteQ->where('cliente_potencials.estado_id', $pendienteId)->whereBetween('cliente_potencials.created_at', [$inicioMes, $finMes]);
        } elseif ($tipo === 'devueltas') {
            $baseQ->where('base_asignadas.estado_id', $devueltaId)->whereBetween('base_asignadas.updated_at', [$inicioMes, $finMes]);
            $clienteQ->where('cliente_potencials.estado_id', $devueltaId)->whereBetween('cliente_potencials.updated_at', [$inicioMes, $finMes]);
        } elseif ($tipo === 'no_efectivos') {
            $baseQ->where('base_asignadas.estado_id', $cerradoId)
                ->where('base_asignadas.efectivo', false)
                ->where(function ($q) use ($inicioMes, $finMes, $cerradoId) {
                    $q->whereBetween('base_asignadas.cierre_solicitado_at', [$inicioMes, $finMes])
                        ->orWhereExists(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                            $sub->selectRaw('1')
                                ->from('gestions')
                                ->whereColumn('gestions.base_asignada_id', 'base_asignadas.id')
                                ->where('gestions.estado_id', $cerradoId)
                                ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                        });
                });
            if (Schema::hasColumn('cliente_potencials', 'efectivo')) {
                $clienteQ->where('cliente_potencials.estado_id', $cerradoId)
                    ->where('cliente_potencials.efectivo', false)
                    ->where(function ($q) use ($inicioMes, $finMes, $cerradoId) {
                        if (Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at')) {
                            $q->whereBetween('cliente_potencials.cierre_solicitado_at', [$inicioMes, $finMes]);
                        }
                        $q->orWhereExists(function ($sub) use ($inicioMes, $finMes, $cerradoId) {
                            $sub->selectRaw('1')
                                ->from('gestions')
                                ->whereColumn('gestions.cliente_potencial_id', 'cliente_potencials.id')
                                ->where('gestions.estado_id', $cerradoId)
                                ->whereBetween('gestions.created_at', [$inicioMes, $finMes]);
                        });
                    });
            } else {
                $clienteQ->whereRaw('1=0');
            }
        }

        $rows = $baseQ->unionAll($clienteQ)->get()->sortByDesc('fecha_ref')->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        $registros = new LengthAwarePaginator($items, $rows->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        return view('dashboard.detalle', compact('registros', 'tipo', 'mes', 'anio', 'periodo', 'inicioMes', 'periodoActualTitulo'));
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
            ->paginate(10)
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
                    ->orWhere('cedula', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%");
            });
        }

        $bases = $query->paginate(10)->withQueryString();
        if (!$this->isSupervisor() && $bases->isEmpty()) {
            abort(403);
        }
        $loteNombre = $bases->first()?->lote_nombre ?? $loteRef;
        $loteUid = $bases->first()?->lote_uid ?? $loteRef;
        $comerciales = User::whereIn('role', $this->rolesGestores())->orderBy('name')->get();
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
        $estados = $this->estadosAsignablesEnCarga();
        $supervisores = User::where('role', 'supervisor')->orderBy('name')->get();
        $comerciales = User::whereIn('role', $this->rolesGestores())->orderBy('name')->get();
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
            'telefono' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'origen' => ['required', 'in:' . implode(',', self::ORIGENES_BASE)],
            'supervisor_id' => ['required', 'exists:users,id'],
            'asesor_id' => ['required', 'exists:users,id'],
            'observaciones' => ['required', 'string'],
        ]);
        $data['estado_id'] = $this->estadoNuevoId();
        $data['lote_nombre'] = trim((string) ($data['lote_nombre'] ?? '')) ?: 'INDIVIDUAL';
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
        $desembolsoEstados = self::ESTADOS_DESEMBOLSO;
        $tiempoInvertidoRegistroMin = (int) Gestion::where('base_asignada_id', $base->id)->sum('minutos_invertidos');
        $solicitudCierre = Gestion::where('base_asignada_id', $base->id)
            ->where('estado_id', Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id'))
            ->latest('created_at')
            ->first();
        $vinculacionCierre = $solicitudCierre?->es_vinculacion
            ? 'SI'
            : 'N/A';
        $productoCierre = 'N/A';
        if ($solicitudCierre?->es_ahorro) {
            $productoCierre = 'Ahorro: ' . ($solicitudCierre->linea_ahorro ?: 'Sin linea') . ' - $' . number_format((float) ($solicitudCierre->monto_ahorro ?? 0), 0, ',', '.');
        } elseif ($solicitudCierre?->es_vinculacion) {
            $productoCierre = 'Credito: ' . ($solicitudCierre->linea_credito_gestion ?: 'Sin linea');
        }
        return view('base_asignadas.show', compact('base', 'gestiones', 'historicoCedula', 'estados', 'lineasCredito', 'desembolsoEstados', 'tiempoInvertidoRegistroMin', 'vinculacionCierre', 'productoCierre'));
    }

    public function historicoCedula(Request $request)
    {
        $criterio = trim((string) $request->input('q', ''));
        $registros = null;
        $gestiones = null;

        if ($criterio !== '') {
            $baseQ = DB::table('base_asignadas')
                ->leftJoin('estados', 'estados.id', '=', 'base_asignadas.estado_id')
                ->leftJoin('users', 'users.id', '=', 'base_asignadas.asesor_id')
                ->where(function ($q) use ($criterio) {
                    $q->where('base_asignadas.cedula', 'like', "%{$criterio}%")
                        ->orWhere('base_asignadas.nombre', 'like', "%{$criterio}%")
                        ->orWhere('base_asignadas.telefono', 'like', "%{$criterio}%");
                })
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
                ->where(function ($q) use ($criterio) {
                    $q->where('cliente_potencials.cedula', 'like', "%{$criterio}%")
                        ->orWhere('cliente_potencials.nombre', 'like', "%{$criterio}%")
                        ->orWhere('cliente_potencials.telefono', 'like', "%{$criterio}%");
                })
                ->selectRaw("'cliente_potencial' as tipo_registro")
                ->selectRaw('cliente_potencials.id as registro_id')
                ->selectRaw('cliente_potencials.created_at as created_at')
                ->selectRaw('COALESCE(cliente_potencials.ultima_gestion_at, cliente_potencials.updated_at) as ultima_gestion_at')
                ->selectRaw("COALESCE(cliente_potencials.lote_nombre, 'CLIENTE POTENCIAL') as lote")
                ->selectRaw('cliente_potencials.nombre as nombre')
                ->selectRaw('cliente_potencials.cedula as cedula')
                ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
                ->selectRaw("COALESCE(users.name, 'Sin asignar') as asesor_nombre");
            $rowsReg = $baseQ->unionAll($cpQ)->get()->sortByDesc('created_at')->values();
            $pageReg = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $itemsReg = $rowsReg->slice(($pageReg - 1) * $perPage, $perPage)->values();
            $registros = new \Illuminate\Pagination\LengthAwarePaginator($itemsReg, $rowsReg->count(), $perPage, $pageReg, [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => 'page',
            ]);

            $gestBaseQ = DB::table('gestions')
                ->leftJoin('estados', 'estados.id', '=', 'gestions.estado_id')
                ->leftJoin('users', 'users.id', '=', 'gestions.asesor_id')
                ->leftJoin('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
                ->whereNotNull('gestions.base_asignada_id')
                ->where(function ($q) use ($criterio) {
                    $q->where('base_asignadas.cedula', 'like', "%{$criterio}%")
                        ->orWhere('base_asignadas.nombre', 'like', "%{$criterio}%")
                        ->orWhere('base_asignadas.telefono', 'like', "%{$criterio}%");
                })
                ->selectRaw("'base' as tipo_registro")
                ->selectRaw('gestions.created_at as created_at')
                ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
                ->selectRaw('gestions.tipo as canal')
                ->selectRaw('gestions.detalle as detalle')
                ->selectRaw('base_asignadas.lote_nombre as lote')
                ->selectRaw("COALESCE(users.name, 'N/A') as asesor_nombre")
                ->selectRaw('base_asignadas.id as registro_id');
            $gestCpQ = DB::table('gestions')
                ->leftJoin('estados', 'estados.id', '=', 'gestions.estado_id')
                ->leftJoin('users', 'users.id', '=', 'gestions.asesor_id')
                ->leftJoin('cliente_potencials', 'cliente_potencials.id', '=', 'gestions.cliente_potencial_id')
                ->whereNotNull('gestions.cliente_potencial_id')
                ->where(function ($q) use ($criterio) {
                    $q->where('cliente_potencials.cedula', 'like', "%{$criterio}%")
                        ->orWhere('cliente_potencials.nombre', 'like', "%{$criterio}%")
                        ->orWhere('cliente_potencials.telefono', 'like', "%{$criterio}%");
                })
                ->selectRaw("'cliente_potencial' as tipo_registro")
                ->selectRaw('gestions.created_at as created_at')
                ->selectRaw("COALESCE(estados.nombre, 'Sin estado') as estado_nombre")
                ->selectRaw('gestions.tipo as canal')
                ->selectRaw('gestions.detalle as detalle')
                ->selectRaw("COALESCE(cliente_potencials.lote_nombre, 'CLIENTE POTENCIAL') as lote")
                ->selectRaw("COALESCE(users.name, 'N/A') as asesor_nombre")
                ->selectRaw('cliente_potencials.id as registro_id');
            $rowsGest = $gestBaseQ->unionAll($gestCpQ)->get()->sortByDesc('created_at')->values();
            $pageGest = (int) request()->input('hist_page', 1);
            $itemsGest = $rowsGest->slice(($pageGest - 1) * $perPage, $perPage)->values();
            $gestiones = new \Illuminate\Pagination\LengthAwarePaginator($itemsGest, $rowsGest->count(), $perPage, $pageGest, [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => 'hist_page',
            ]);
        }

        return view('base_asignadas.historico_cedula', compact('criterio', 'registros', 'gestiones'));
    }

    public function cerradasComercial()
    {
        abort_unless(in_array(auth()->user()?->role, $this->rolesGestores(), true), 403);
        $cerradoId = Estado::where('slug', 'cerrado')->value('id');
        $q = trim((string) request('q', ''));
        $estadoFiltro = request()->filled('estado_id') ? (int) request('estado_id') : null;
        $bases = DB::table('base_asignadas')
            ->leftJoin('estados', 'estados.id', '=', 'base_asignadas.estado_id')
            ->where('base_asignadas.asesor_id', auth()->id())
            ->where('base_asignadas.estado_id', $cerradoId)
            ->selectRaw("'base' as tipo_registro")
            ->selectRaw('base_asignadas.id as registro_id')
            ->selectRaw('base_asignadas.lote_nombre as lote')
            ->selectRaw('base_asignadas.nombre as cliente')
            ->selectRaw('base_asignadas.cedula as cedula')
            ->selectRaw('base_asignadas.linea_credito as linea_credito')
            ->selectRaw('base_asignadas.monto_solicitado as monto_solicitado')
            ->selectRaw('base_asignadas.monto_linea_credito as monto_aprobado')
            ->selectRaw('base_asignadas.asignado_at as fecha_asignacion')
            ->selectRaw('base_asignadas.ultima_gestion_at as ultima_modificacion')
            ->selectRaw("COALESCE(estados.nombre, 'N/A') as estado_nombre");
        if ($q !== '') {
            $bases->where(function ($sub) use ($q) {
                $sub->where('base_asignadas.nombre', 'like', "%{$q}%")
                    ->orWhere('base_asignadas.telefono', 'like', "%{$q}%");
            });
        }
        if (!is_null($estadoFiltro)) {
            $bases->where('base_asignadas.estado_id', $estadoFiltro);
        }

        $clientes = DB::table('cliente_potencials')
            ->leftJoin('estados', 'estados.id', '=', 'cliente_potencials.estado_id')
            ->where('cliente_potencials.asesor_id', auth()->id())
            ->where('cliente_potencials.estado_id', $cerradoId)
            ->selectRaw("'cliente_potencial' as tipo_registro")
            ->selectRaw('cliente_potencials.id as registro_id')
            ->selectRaw("COALESCE(cliente_potencials.lote_nombre, 'CLIENTE POTENCIAL') as lote")
            ->selectRaw('cliente_potencials.nombre as cliente')
            ->selectRaw('cliente_potencials.cedula as cedula')
            ->selectRaw('cliente_potencials.linea_credito as linea_credito')
            ->selectRaw('cliente_potencials.monto_solicitado as monto_solicitado')
            ->selectRaw('cliente_potencials.monto_linea_credito as monto_aprobado')
            ->selectRaw('cliente_potencials.created_at as fecha_asignacion')
            ->selectRaw('COALESCE(cliente_potencials.ultima_gestion_at, cliente_potencials.updated_at) as ultima_modificacion')
            ->selectRaw("COALESCE(estados.nombre, 'N/A') as estado_nombre");
        if ($q !== '') {
            $clientes->where(function ($sub) use ($q) {
                $sub->where('cliente_potencials.nombre', 'like', "%{$q}%")
                    ->orWhere('cliente_potencials.telefono', 'like', "%{$q}%");
            });
        }
        if (!is_null($estadoFiltro)) {
            $clientes->where('cliente_potencials.estado_id', $estadoFiltro);
        }

        $rows = $bases->unionAll($clientes)->get()->sortByDesc('ultima_modificacion')->values();
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        $registros = new \Illuminate\Pagination\LengthAwarePaginator($items, $rows->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        $estadosFiltro = Estado::where('activo', true)->orderBy('nombre')->get();
        return view('base_asignadas.cerradas', compact('registros', 'estadosFiltro'));
    }

    public function pendientesComercial()
    {
        abort_unless(in_array(auth()->user()?->role, $this->rolesGestores(), true), 403);
        $pendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $bases = DB::table('base_asignadas')
            ->leftJoin('estados', 'estados.id', '=', 'base_asignadas.estado_id')
            ->where('base_asignadas.asesor_id', auth()->id())
            ->where('base_asignadas.estado_id', $pendienteId)
            ->selectRaw("'base' as tipo_registro")
            ->selectRaw('base_asignadas.id as registro_id')
            ->selectRaw('base_asignadas.lote_nombre as lote')
            ->selectRaw('base_asignadas.nombre as cliente')
            ->selectRaw('base_asignadas.cedula as cedula')
            ->selectRaw("COALESCE(estados.nombre, 'N/A') as estado_nombre")
            ->selectRaw('base_asignadas.efectivo as efectivo')
            ->selectRaw('base_asignadas.monto_linea_credito as monto')
            ->selectRaw('base_asignadas.cierre_solicitado_at as fecha_ref');

        $clientes = DB::table('cliente_potencials')
            ->leftJoin('estados', 'estados.id', '=', 'cliente_potencials.estado_id')
            ->where('cliente_potencials.asesor_id', auth()->id())
            ->where('cliente_potencials.estado_id', $pendienteId)
            ->selectRaw("'cliente_potencial' as tipo_registro")
            ->selectRaw('cliente_potencials.id as registro_id')
            ->selectRaw("COALESCE(cliente_potencials.lote_nombre, 'CLIENTE POTENCIAL') as lote")
            ->selectRaw('cliente_potencials.nombre as cliente')
            ->selectRaw('cliente_potencials.cedula as cedula')
            ->selectRaw("COALESCE(estados.nombre, 'N/A') as estado_nombre")
            ->selectRaw('cliente_potencials.efectivo as efectivo')
            ->selectRaw('cliente_potencials.monto_linea_credito as monto')
            ->selectRaw('cliente_potencials.cierre_solicitado_at as fecha_ref');

        $rows = $bases->unionAll($clientes)->get()->sortByDesc('fecha_ref')->values();
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        $registros = new \Illuminate\Pagination\LengthAwarePaginator($items, $rows->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        return view('base_asignadas.pendientes_comercial', compact('registros'));
    }

    public function comercialesSupervisor()
    {
        $this->forbidIfNotSupervisor();
        $comerciales = User::whereIn('role', $this->rolesGestores())
            ->withCount(['basesAsignadas', 'clientesPotenciales'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        $comerciales->getCollection()->transform(function ($u) {
            $u->total_registros = (int) ($u->bases_asignadas_count ?? 0) + (int) ($u->clientes_potenciales_count ?? 0);
            return $u;
        });

        return view('base_asignadas.comerciales', compact('comerciales'));
    }

    public function gestionComercialSupervisor(Request $request, string $comercialId)
    {
        $this->forbidIfNotSupervisor();
        $comercial = User::whereIn('role', $this->rolesGestores())->findOrFail($comercialId);
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

        $lotes = $query->paginate(10)->withQueryString();
        $clientesPotencialesQuery = ClientePotencial::with(['estado'])
            ->where('asesor_id', $comercial->id)
            ->orderByDesc('updated_at');
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $clientesPotencialesQuery->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%")
                    ->orWhere('empresa', 'like', "%{$q}%");
            });
        }
        $clientesPotenciales = $clientesPotencialesQuery->paginate(10, ['*'], 'cp_page')->withQueryString();

        return view('base_asignadas.gestion_comercial', compact('comercial', 'lotes', 'clientesPotenciales'));
    }

    public function gestionComercialLoteSupervisor(Request $request, string $comercialId, string $loteRef)
    {
        $this->forbidIfNotSupervisor();
        $comercial = User::whereIn('role', $this->rolesGestores())->findOrFail($comercialId);

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

        $bases = $query->paginate(10)->withQueryString();
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
            ->paginate(10)
            ->withQueryString();
        $ordenClientesPendientes = Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at')
            ? 'cierre_solicitado_at'
            : 'updated_at';
        $clientesPendientes = ClientePotencial::with(['asesor', 'estado'])
            ->where('estado_id', $pendienteId)
            ->latest($ordenClientesPendientes)
            ->paginate(10, ['*'], 'cp_page')
            ->withQueryString();
        $basesDesembolsoPendientes = BaseAsignada::with(['asesor', 'estado'])
            ->whereNotNull('desembolso_estado_pendiente')
            ->latest('desembolso_solicitado_at')
            ->paginate(10, ['*'], 'bd_page')
            ->withQueryString();
        $clientesDesembolsoPendientes = ClientePotencial::with(['asesor', 'estado'])
            ->whereNotNull('desembolso_estado_pendiente')
            ->latest('desembolso_solicitado_at')
            ->paginate(10, ['*'], 'cd_page')
            ->withQueryString();

        return view('base_asignadas.pendientes', compact('bases', 'clientesPendientes', 'basesDesembolsoPendientes', 'clientesDesembolsoPendientes'));
    }

    public function solicitarDesembolso(Request $request, string $id)
    {
        $base = BaseAsignada::with('estado')->findOrFail($id);
        if (!$this->isSupervisor() && $base->asesor_id !== auth()->id()) {
            abort(403);
        }
        if ($base->estado?->slug !== 'cerrado') {
            return back()->withErrors(['desembolso_estado' => 'Solo se puede cambiar el desembolso cuando el registro esta cerrado.']);
        }

        $data = $request->validate([
            'desembolso_estado' => ['required', 'in:' . implode(',', self::ESTADOS_DESEMBOLSO)],
            'detalle' => ['nullable', 'string', 'max:2000'],
        ]);
        $esDesembolsado = strtolower(trim($data['desembolso_estado'])) === 'desembolsado';

        if ($this->isSupervisor()) {
            $base->update([
                'desembolso_estado' => $data['desembolso_estado'],
                'desembolso_estado_pendiente' => null,
                'desembolso_solicitado_at' => null,
                'desembolso_solicitado_por' => null,
                'desembolso_aprobado_at' => $esDesembolsado ? now() : null,
                'desembolso_motivo_devolucion' => null,
                'ultima_gestion_at' => now(),
            ]);

            Gestion::create([
                'asesor_id' => auth()->id(),
                'estado_id' => $base->estado_id,
                'base_asignada_id' => $base->id,
                'tipo' => 'desembolso_supervisor',
                'detalle' => trim('Supervisor actualizo estado desembolso a: ' . $data['desembolso_estado'] . '. ' . ($data['detalle'] ?? '')),
            ]);

            return back()->with('ok', 'Estado de desembolso actualizado.');
        }

        if (!$esDesembolsado) {
            $base->update([
                'desembolso_estado' => $data['desembolso_estado'],
                'desembolso_estado_pendiente' => null,
                'desembolso_solicitado_at' => null,
                'desembolso_solicitado_por' => null,
                'desembolso_aprobado_at' => null,
                'desembolso_motivo_devolucion' => null,
                'ultima_gestion_at' => now(),
            ]);

            Gestion::create([
                'asesor_id' => auth()->id(),
                'estado_id' => $base->estado_id,
                'base_asignada_id' => $base->id,
                'tipo' => 'desembolso_directo',
                'detalle' => trim('Cambio directo de desembolso a: ' . $data['desembolso_estado'] . '. ' . ($data['detalle'] ?? '')),
            ]);

            return back()->with('ok', 'Estado de desembolso actualizado.');
        }

        $base->update([
            'desembolso_estado_pendiente' => $data['desembolso_estado'],
            'desembolso_solicitado_at' => now(),
            'desembolso_solicitado_por' => auth()->id(),
            'desembolso_motivo_devolucion' => null,
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $base->estado_id,
            'base_asignada_id' => $base->id,
            'tipo' => 'solicitud_desembolso',
            'detalle' => trim('Solicitud cambio desembolso a: ' . $data['desembolso_estado'] . '. ' . ($data['detalle'] ?? '')),
        ]);

        foreach (User::where('role', 'supervisor')->pluck('id') as $supervisorId) {
            AppNotification::create([
                'user_id' => $supervisorId,
                'title' => 'Solicitud de desembolso pendiente',
                'message' => "Se solicito cambio de desembolso para {$base->nombre} ({$base->cedula}) por " . auth()->user()?->name . '.',
                'type' => 'desembolso_pendiente',
                'related_id' => $base->id,
                'related_type' => BaseAsignada::class,
                'event_at' => now(),
            ]);
        }

        return back()->with('ok', 'Cambio de desembolso enviado a aprobacion del supervisor.');
    }

    public function aprobarDesembolso(string $id)
    {
        $this->forbidIfNotSupervisor();
        $base = BaseAsignada::findOrFail($id);
        if (!$base->desembolso_estado_pendiente) {
            return back()->withErrors(['desembolso_estado' => 'No hay solicitud de desembolso pendiente.']);
        }

        $nuevoEstado = $base->desembolso_estado_pendiente;
        $base->update([
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
            'estado_id' => $base->estado_id,
            'base_asignada_id' => $base->id,
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
        $base = BaseAsignada::findOrFail($id);
        if (!$base->desembolso_estado_pendiente) {
            return back()->withErrors(['desembolso_estado' => 'No hay solicitud de desembolso pendiente.']);
        }
        $pendiente = $base->desembolso_estado_pendiente;
        $base->update([
            'desembolso_estado_pendiente' => null,
            'desembolso_solicitado_at' => null,
            'desembolso_solicitado_por' => null,
            'desembolso_motivo_devolucion' => $data['motivo_devolucion'],
            'ultima_gestion_at' => now(),
        ]);

        Gestion::create([
            'asesor_id' => auth()->id(),
            'estado_id' => $base->estado_id,
            'base_asignada_id' => $base->id,
            'tipo' => 'devolucion_desembolso',
            'detalle' => "Supervisor devolvio cambio de desembolso ({$pendiente}). Motivo: {$data['motivo_devolucion']}",
        ]);

        return back()->with('ok', 'Cambio de desembolso devuelto.');
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
            'cierre_aprobado_at' => now(),
            'desembolso_aprobado_at' => now(),
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
            'desembolso_estado' => null,
            'desembolso_estado_pendiente' => null,
            'desembolso_solicitado_at' => null,
            'desembolso_solicitado_por' => null,
            'desembolso_aprobado_at' => null,
            'desembolso_motivo_devolucion' => null,
            'cierre_solicitado_at' => null,
            'cierre_solicitado_por' => null,
            'cierre_aprobado_at' => null,
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
            'cierre_aprobado_at' => null,
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
            'cierre_aprobado_at' => null,
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

    public function actualizarDatosBasicos(Request $request, string $id)
    {
        $base = BaseAsignada::findOrFail($id);
        if (!$this->isSupervisor() && $base->asesor_id !== auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'empresa' => ['nullable', 'string', 'max:255'],
        ]);

        $data['persona_id'] = $this->resolvePersonaId(
            $data['cedula'] ?? null,
            $data['nombre'] ?? null,
            $data['telefono'] ?? null,
            $base->email ?? null
        );

        $base->update($data);

        return redirect()->route('base-asignada.show', $base->id)->with('ok', 'Datos basicos actualizados.');
    }

    public function retomarRegistro(string $id)
    {
        $base = BaseAsignada::with('estado')->findOrFail($id);
        if ($base->estado?->slug !== 'cerrado') {
            return back()->withErrors(['estado_id' => 'Solo se pueden retomar registros cerrados.']);
        }

        $nuevo = BaseAsignada::create([
            'supervisor_id' => $base->supervisor_id ?: auth()->id(),
            'lote_uid' => $this->buildLoteUid('RETOMADO'),
            'lote_nombre' => 'RETOMADO',
            'asesor_id' => auth()->id(),
            'asignado_at' => now(),
            'estado_id' => $this->estadoNuevoId(),
            'persona_id' => $this->resolvePersonaId($base->cedula, $base->nombre, $base->telefono, $base->email),
            'nombre' => $base->nombre,
            'cedula' => $base->cedula,
            'telefono' => $base->telefono,
            'email' => $base->email,
            'empresa' => $base->empresa,
            'linea_credito' => $base->linea_credito,
            'origen' => 'retomado',
            'observaciones' => "Registro retomado desde Base asignada #{$base->id}.",
        ]);

        return redirect()->route('base-asignada.show', $nuevo->id)->with('ok', 'Registro retomado y creado como nuevo.');
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
        $comerciales = User::whereIn('role', $this->rolesGestores())->orderBy('name')->get();
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

        $permitidas = ['nombre', 'telefono', 'cedula', 'linea_credito', 'email', 'empresa', 'origen', 'fuente', 'observaciones', 'comercial_email', 'asesor_email'];
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
        $estadoImport = $this->estadoNuevoId();
        $observacionesImport = trim((string) $request->input('observaciones'));
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
            $origenFila = $get('origen') ?: $get('fuente');
            $observaciones = $get('observaciones') ?: $observacionesImport;
            $comercialEmail = $get('comercial_email') ?: $get('asesor_email');

            if ($nombre === '' || $telefono === '') {
                $omitidos++;
                continue;
            }

            $comercial = null;
            if ($comercialEmail !== '') {
                $comercial = User::where('email', $comercialEmail)->whereIn('role', $this->rolesGestores())->first();
            }
            if ($comercialEmail !== '' && !$comercial) {
                $omitidos++;
                continue;
            }

            $estadoId = $estadoImport;

            if ($lineaCredito !== '' && !in_array($lineaCredito, self::LINEAS_CREDITO, true)) {
                $omitidos++;
                continue;
            }
            if ($origenFila !== '' && !in_array($origenFila, self::ORIGENES_BASE, true)) {
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
                'origen' => $origenFila ?: $origenImport,
                'observaciones' => $observaciones ?: null,
            ]);

            $creados++;
        }

        fclose($file);

        return redirect()->route('base-asignada.index')->with('ok', "Importacion completada. Creados: {$creados}. Omitidos: {$omitidos}.");
    }

    public function plantillaCsv()
    {
        $this->forbidIfNotSupervisor();

        $headers = ['nombre', 'telefono', 'cedula', 'linea_credito', 'email', 'empresa', 'origen', 'observaciones', 'comercial_email'];
        $example = ['Juan Perez', '3001234567', '12345678', 'LIBRE INVERSION', 'cliente@empresa.com', 'Empresa SAS', 'llamada', 'Cliente interesado', 'comercial@empresa.com'];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'plantilla_base_asignada.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

        $comercialesValidos = User::whereIn('id', $idsComerciales)->whereIn('role', $this->rolesGestores())->pluck('id')->values();
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
