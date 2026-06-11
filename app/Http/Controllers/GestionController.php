<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\BaseAsignada;
use App\Models\ClientePotencial;
use App\Models\Estado;
use App\Models\Gestion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GestionController extends Controller
{
    private const LINEAS_CREDITO = [
        'LIBRE INVERSION',
        'CREDIAPORTES',
        'CREDITO EDUCATIVO',
        'CREDITO ROTATIVO',
        'CREDITO PRIMA',
        'ADELANTO DE SALARIO',
    ];

    private const LINEAS_AHORRO = [
        'CDAT',
        'AHORRO CONTRACTUAL',
    ];

    private const ESTADOS_DESEMBOLSO = [
        'Por desembolsar',
        'desembolsado',
        'aplazado',
        'negado',
        'desistido',
    ];

    public function store(Request $request)
    {
        $user = auth()->user();
        $esSupervisor = $user?->role === 'supervisor';
        $base = null;
        $cliente = null;

        $data = $request->validate([
            'base_asignada_id' => ['nullable', 'exists:base_asignadas,id'],
            'cliente_potencial_id' => ['nullable', 'exists:cliente_potencials,id'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'tipo' => ['required', 'string', 'max:100'],
            'detalle' => ['required', 'string'],
            'proxima_gestion_at' => ['nullable', 'date'],
            'minutos_invertidos' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'monto_solicitado' => ['nullable', 'regex:/^[0-9]+$/'],
            'efectivo' => ['nullable', 'in:SI,NO'],
            'monto_linea_credito' => ['nullable', 'regex:/^[0-9]+$/'],
            'desembolso_estado' => ['nullable', 'in:' . implode(',', self::ESTADOS_DESEMBOLSO)],
            'es_vinculacion' => ['nullable', 'boolean'],
            'es_ahorro' => ['nullable', 'boolean'],
            'linea_ahorro' => ['nullable', 'in:' . implode(',', self::LINEAS_AHORRO)],
            'monto_ahorro' => ['nullable', 'regex:/^[0-9]+$/'],
            'es_asesoria_comercial' => ['nullable', 'boolean'],
        ]);

        if (!$request->filled('base_asignada_id') && !$request->filled('cliente_potencial_id')) {
            return back()->withErrors(['detalle' => 'Debe asociar la gestion a un registro.'])->withInput();
        }

        if ($request->filled('base_asignada_id')) {
            $base = BaseAsignada::with('estado')->findOrFail($request->input('base_asignada_id'));
            if (!$esSupervisor && $base->asesor_id !== $user?->id) {
                abort(403);
            }
            $esPendienteSupervisor = $base->estado?->slug === 'pendiente-aprobacion-supervisor';
            if ($esPendienteSupervisor) {
                return back()->withErrors(['estado_id' => 'Este registro ya esta en Pendiente de aprobacion (supervisor). Debes esperar aprobacion o devolucion.'])->withInput();
            }
            $esCerrado = $base->estado?->slug === 'cerrado';
            $esEfectiva = $base->estado?->slug === 'efectiva';
            if ($esCerrado || $esEfectiva) {
                return back()->withErrors(['estado_id' => 'Este registro ya esta cerrado/finalizado y no permite mas modificaciones.'])->withInput();
            }
        }

        if ($request->filled('cliente_potencial_id')) {
            $cliente = ClientePotencial::with('estado')->findOrFail($request->input('cliente_potencial_id'));
            if (!$esSupervisor && $cliente->asesor_id !== $user?->id) {
                abort(403);
            }
            $esPendienteSupervisor = $cliente->estado?->slug === 'pendiente-aprobacion-supervisor';
            if ($esPendienteSupervisor) {
                return back()->withErrors(['estado_id' => 'Este registro ya esta en Pendiente de aprobacion (supervisor). Debes esperar aprobacion o devolucion.'])->withInput();
            }
            $esCerrado = $cliente->estado?->slug === 'cerrado';
            $esEfectiva = $cliente->estado?->slug === 'efectiva';
            if ($esCerrado || $esEfectiva) {
                return back()->withErrors(['estado_id' => 'Este registro ya esta cerrado/finalizado y no permite mas modificaciones.'])->withInput();
            }
        }

        $estadoPendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $estadoGestion = $data['estado_id'] ? Estado::find($data['estado_id']) : null;
        if ($estadoGestion?->slug === 'pendiente-aprobacion-supervisor') {
            return back()->withErrors(['estado_id' => 'Ese estado no se puede asignar manualmente.'])->withInput();
        }
        $esCierre = $estadoGestion?->slug === 'cerrado';
        $esVinculacion = $request->boolean('es_vinculacion');
        $esAhorro = $request->boolean('es_ahorro');
        $esAsesoriaComercial = $request->boolean('es_asesoria_comercial');
        if ($esAhorro) {
            $request->validate([
                'linea_ahorro' => ['required', 'in:' . implode(',', self::LINEAS_AHORRO)],
                'monto_ahorro' => ['required', 'regex:/^[0-9]+$/'],
            ]);
        }
        if ($esAsesoriaComercial && !$esCierre) {
            return back()->withErrors(['es_asesoria_comercial' => 'La asesoria comercial solo se puede marcar cuando el estado resultante es Cerrado.'])->withInput();
        }
        if ($esAsesoriaComercial) {
            $esVinculacion = false;
            $esAhorro = false;
            $request->merge([
                'efectivo' => 'NO',
                'linea_credito' => null,
                'monto_linea_credito' => null,
                'desembolso_estado' => null,
                'es_vinculacion' => null,
                'es_ahorro' => null,
                'linea_ahorro' => null,
                'monto_ahorro' => null,
            ]);
        }
        $desembolsoEstadoCierre = $request->input('efectivo') === 'SI'
            ? 'Por desembolsar'
            : $request->input('desembolso_estado');
        $requiereAprobacionSupervisor = $esCierre && $request->input('efectivo') === 'SI';
        if ($esCierre) {
            $request->validate([
                'efectivo' => ['required', 'in:SI,NO'],
                'monto_linea_credito' => [(!$esAhorro && !$esAsesoriaComercial && $request->input('efectivo') === 'SI' ? 'required' : 'nullable'), 'regex:/^[0-9]*$/'],
                'desembolso_estado' => ['nullable', 'in:' . implode(',', self::ESTADOS_DESEMBOLSO)],
            ]);
            if ($requiereAprobacionSupervisor) {
                $data['estado_id'] = $estadoPendienteId;
            }
        }

        if (!$esCierre && empty($data['estado_id'])) {
            if ($base) {
                $data['estado_id'] = $base->estado_id;
            } elseif ($cliente) {
                $data['estado_id'] = $cliente->estado_id;
            }
        }

        if (!$esAhorro && $base && $request->filled('monto_solicitado')) {
            $nuevoMontoSolicitado = (int) $request->input('monto_solicitado');
            $montoActual = is_null($base->monto_solicitado) ? null : (int) $base->monto_solicitado;
            if ($montoActual !== $nuevoMontoSolicitado) {
                $actualTxt = is_null($montoActual) ? 'N/A' : number_format($montoActual, 0, ',', '.');
                $nuevoTxt = number_format($nuevoMontoSolicitado, 0, ',', '.');
                $data['detalle'] = trim($data['detalle']) . " | Monto solicitado: {$actualTxt} -> {$nuevoTxt}";
            }
        }

        $lineaCreditoGestion = ($esAhorro || $esAsesoriaComercial) ? null : $request->input('linea_credito');
        if ($esCierre && $request->input('efectivo') === 'NO') {
            $desembolsoEstadoCierre = null;
        }

        $data['es_vinculacion'] = $esVinculacion;
        $data['es_ahorro'] = $esAhorro;
        $data['es_asesoria_comercial'] = $esAsesoriaComercial;
        $data['linea_credito_gestion'] = $lineaCreditoGestion;
        $data['linea_ahorro'] = $esAhorro ? $request->input('linea_ahorro') : null;
        $data['monto_ahorro'] = $esAhorro ? (int) $request->input('monto_ahorro') : null;

        $data['asesor_id'] = $user?->id;
        $gestion = Gestion::create($data);

        if ($gestion->base_asignada_id) {
            $update = [];
            if (!is_null($gestion->estado_id)) {
                $update['estado_id'] = $gestion->estado_id;
            }
            $update['ultima_gestion_at'] = now();
            if ($esAhorro || $esAsesoriaComercial) {
                $update['linea_credito'] = null;
                if ($esAhorro) {
                    $update['monto_solicitado'] = null;
                }
            } elseif ($request->filled('linea_credito')) {
                $update['linea_credito'] = $request->input('linea_credito');
            }
            if (!$esAhorro && !$esAsesoriaComercial && $request->filled('monto_solicitado')) {
                $update['monto_solicitado'] = (int) $request->input('monto_solicitado');
            }

            if ($esCierre) {
                $update['efectivo'] = $request->input('efectivo') === 'SI';
                $update['monto_linea_credito'] = (!$esAhorro && !$esAsesoriaComercial && $request->input('efectivo') === 'SI')
                    ? (int) $request->input('monto_linea_credito')
                    : null;
                $update['desembolso_estado'] = ($esAhorro || $esAsesoriaComercial) ? null : $desembolsoEstadoCierre;
                $update['desembolso_estado_pendiente'] = null;
                $update['desembolso_solicitado_at'] = null;
                $update['desembolso_solicitado_por'] = null;
                $update['desembolso_motivo_devolucion'] = null;
                $update['cierre_solicitado_at'] = now();
                $update['cierre_solicitado_por'] = auth()->id();
                $update['motivo_devolucion'] = null;
            }

            BaseAsignada::whereKey($gestion->base_asignada_id)->update($update);

            if (!empty($data['proxima_gestion_at']) && $base?->asesor_id) {
                $fechaProxima = \Carbon\Carbon::parse($data['proxima_gestion_at']);
                if ($fechaProxima->isSameDay(now())) {
                    AppNotification::create([
                        'user_id' => $base->asesor_id,
                        'title' => 'Gestion programada para hoy',
                        'message' => "Tienes una proxima gestion hoy para {$base->nombre} ({$base->cedula}).",
                        'type' => 'proxima_gestion',
                        'related_id' => $base->id,
                        'related_type' => BaseAsignada::class,
                        'event_at' => $fechaProxima,
                    ]);
                }
            }

            if ($requiereAprobacionSupervisor) {
                $supervisores = User::where('role', 'supervisor')->pluck('id');
                foreach ($supervisores as $supervisorId) {
                    AppNotification::create([
                        'user_id' => $supervisorId,
                        'title' => 'Solicitud de cierre pendiente',
                        'message' => "Se solicito cierre para {$base?->nombre} ({$base?->cedula}) por {$user?->name}.",
                        'type' => 'cierre_pendiente',
                        'related_id' => $base?->id,
                        'related_type' => BaseAsignada::class,
                        'event_at' => now(),
                    ]);
                }
            }

            $msg = $requiereAprobacionSupervisor
                ? 'Gestion enviada a aprobacion del supervisor.'
                : ($esCierre ? 'Gestion cerrada sin aprobacion de supervisor.' : 'Gestion registrada.');
            return redirect()->route('base-asignada.show', $gestion->base_asignada_id)->with('ok', $msg);
        }

        $update = [];
        $colsCliente = Schema::getColumnListing('cliente_potencials');
        $hasClienteCol = fn (string $col) => in_array($col, $colsCliente, true);
        if (!is_null($gestion->estado_id)) {
            $update['estado_id'] = $gestion->estado_id;
        }
        if ($hasClienteCol('ultima_gestion_at')) {
            $update['ultima_gestion_at'] = now();
        }
        if (($esAhorro || $esAsesoriaComercial) && $hasClienteCol('linea_credito')) {
            $update['linea_credito'] = null;
            if ($esAhorro && $hasClienteCol('monto_solicitado')) {
                $update['monto_solicitado'] = null;
            }
        } elseif ($hasClienteCol('linea_credito') && $request->filled('linea_credito')) {
            $update['linea_credito'] = $request->input('linea_credito');
        }
        if (!$esAhorro && !$esAsesoriaComercial && $hasClienteCol('monto_solicitado') && $request->filled('monto_solicitado')) {
            $update['monto_solicitado'] = (int) $request->input('monto_solicitado');
        }

        if ($esCierre) {
            if ($hasClienteCol('efectivo')) {
                $update['efectivo'] = $request->input('efectivo') === 'SI';
            }
            if ($hasClienteCol('monto_linea_credito')) {
                $update['monto_linea_credito'] = (!$esAhorro && !$esAsesoriaComercial && $request->input('efectivo') === 'SI')
                    ? (int) $request->input('monto_linea_credito')
                    : null;
            }
            if ($hasClienteCol('desembolso_estado')) {
                $update['desembolso_estado'] = ($esAhorro || $esAsesoriaComercial) ? null : $desembolsoEstadoCierre;
            }
            if ($hasClienteCol('desembolso_estado_pendiente')) {
                $update['desembolso_estado_pendiente'] = null;
            }
            if ($hasClienteCol('desembolso_solicitado_at')) {
                $update['desembolso_solicitado_at'] = null;
            }
            if ($hasClienteCol('desembolso_solicitado_por')) {
                $update['desembolso_solicitado_por'] = null;
            }
            if ($hasClienteCol('desembolso_motivo_devolucion')) {
                $update['desembolso_motivo_devolucion'] = null;
            }
            if ($hasClienteCol('cierre_solicitado_at')) {
                $update['cierre_solicitado_at'] = now();
            }
            if ($hasClienteCol('cierre_solicitado_por')) {
                $update['cierre_solicitado_por'] = auth()->id();
            }
            if ($hasClienteCol('motivo_devolucion')) {
                $update['motivo_devolucion'] = null;
            }
        }

        ClientePotencial::whereKey($gestion->cliente_potencial_id)->update($update);

        if ($requiereAprobacionSupervisor) {
            $supervisores = User::where('role', 'supervisor')->pluck('id');
            foreach ($supervisores as $supervisorId) {
                AppNotification::create([
                    'user_id' => $supervisorId,
                    'title' => 'Solicitud de cierre pendiente',
                    'message' => "Se solicito cierre para cliente potencial {$cliente?->nombre} por {$user?->name}.",
                    'type' => 'cierre_pendiente_cliente',
                    'related_id' => $cliente?->id,
                    'related_type' => ClientePotencial::class,
                    'event_at' => now(),
                ]);
            }
        }

        $msg = $requiereAprobacionSupervisor
            ? 'Gestion enviada a aprobacion del supervisor.'
            : ($esCierre ? 'Gestion cerrada sin aprobacion de supervisor.' : 'Gestion registrada.');
        return redirect()->route('clientes-potenciales.show', $gestion->cliente_potencial_id)->with('ok', $msg);
    }
}
