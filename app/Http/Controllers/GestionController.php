<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\BaseAsignada;
use App\Models\ClientePotencial;
use App\Models\Estado;
use App\Models\Gestion;
use App\Models\User;
use Illuminate\Http\Request;

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
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'monto_solicitado' => ['nullable', 'regex:/^[0-9]+$/'],
            'efectivo' => ['nullable', 'in:SI,NO'],
            'monto_linea_credito' => ['nullable', 'regex:/^[0-9]+$/'],
        ]);

        if (!$request->filled('base_asignada_id') && !$request->filled('cliente_potencial_id')) {
            return back()->withErrors(['detalle' => 'Debe asociar la gestion a un registro.'])->withInput();
        }

        if ($request->filled('base_asignada_id')) {
            $base = BaseAsignada::with('estado')->findOrFail($request->input('base_asignada_id'));
            if (!$esSupervisor && $base->asesor_id !== $user?->id) {
                abort(403);
            }
            $esCerrado = $base->estado?->slug === 'cerrado';
            if ($esCerrado && !$esSupervisor) {
                return back()->withErrors(['estado_id' => 'Este registro esta cerrado. Solo supervisor puede reabrirlo.'])->withInput();
            }
        }

        if ($request->filled('cliente_potencial_id')) {
            $cliente = ClientePotencial::findOrFail($request->input('cliente_potencial_id'));
            if (!$esSupervisor && $cliente->asesor_id !== $user?->id) {
                abort(403);
            }
        }

        $estadoPendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $estadoGestion = $data['estado_id'] ? Estado::find($data['estado_id']) : null;
        $esCierre = $estadoGestion?->slug === 'cerrado';
        if ($esCierre) {
            $request->validate([
                'efectivo' => ['required', 'in:SI,NO'],
                'monto_linea_credito' => [($request->input('efectivo') === 'SI' ? 'required' : 'nullable'), 'regex:/^[0-9]*$/'],
            ]);
            $data['estado_id'] = $estadoPendienteId;
        }

        if (!$esCierre && empty($data['estado_id'])) {
            if ($base) {
                $data['estado_id'] = $base->estado_id;
            } elseif ($cliente) {
                $data['estado_id'] = $cliente->estado_id;
            }
        }

        if ($base && $request->filled('monto_solicitado')) {
            $nuevoMontoSolicitado = (int) $request->input('monto_solicitado');
            $montoActual = is_null($base->monto_solicitado) ? null : (int) $base->monto_solicitado;
            if ($montoActual !== $nuevoMontoSolicitado) {
                $actualTxt = is_null($montoActual) ? 'N/A' : number_format($montoActual, 0, ',', '.');
                $nuevoTxt = number_format($nuevoMontoSolicitado, 0, ',', '.');
                $data['detalle'] = trim($data['detalle']) . " | Monto solicitado: {$actualTxt} -> {$nuevoTxt}";
            }
        }

        $data['asesor_id'] = $user?->id;
        $gestion = Gestion::create($data);

        if ($gestion->base_asignada_id) {
            $update = [];
            if (!is_null($gestion->estado_id)) {
                $update['estado_id'] = $gestion->estado_id;
            }
            $update['ultima_gestion_at'] = now();
            if ($request->filled('linea_credito')) {
                $update['linea_credito'] = $request->input('linea_credito');
            }
            if ($request->filled('monto_solicitado')) {
                $update['monto_solicitado'] = (int) $request->input('monto_solicitado');
            }

            if ($esCierre) {
                $update['efectivo'] = $request->input('efectivo') === 'SI';
                $update['monto_linea_credito'] = $request->input('efectivo') === 'SI'
                    ? (int) $request->input('monto_linea_credito')
                    : null;
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

            if ($esCierre) {
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

            $msg = $esCierre
                ? 'Gestion enviada a aprobacion del supervisor.'
                : 'Gestion registrada.';
            return redirect()->route('base-asignada.show', $gestion->base_asignada_id)->with('ok', $msg);
        }

        ClientePotencial::whereKey($gestion->cliente_potencial_id)->update(['estado_id' => $gestion->estado_id]);
        return redirect()->route('clientes-potenciales.show', $gestion->cliente_potencial_id)->with('ok', 'Gestion registrada.');
    }
}
