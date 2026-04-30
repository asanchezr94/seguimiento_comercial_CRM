<?php

namespace App\Http\Controllers;

use App\Models\BaseAsignada;
use App\Models\ClientePotencial;
use App\Models\Estado;
use App\Models\Gestion;
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
        $data = $request->validate([
            'base_asignada_id' => ['nullable', 'exists:base_asignadas,id'],
            'cliente_potencial_id' => ['nullable', 'exists:cliente_potencials,id'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'tipo' => ['required', 'string', 'max:100'],
            'detalle' => ['required', 'string'],
            'proxima_gestion_at' => ['nullable', 'date'],
            'linea_credito' => ['nullable', 'in:' . implode(',', self::LINEAS_CREDITO)],
            'efectivo' => ['nullable', 'in:SI,NO'],
            'monto_linea_credito' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (!$request->filled('base_asignada_id') && !$request->filled('cliente_potencial_id')) {
            return back()->withErrors(['detalle' => 'Debe asociar la gestion a un registro.'])->withInput();
        }

        if ($request->filled('base_asignada_id')) {
            $base = BaseAsignada::with('estado')->findOrFail($request->input('base_asignada_id'));
            $esCerrado = $base->estado?->slug === 'cerrado';
            $esSupervisor = auth()->user()?->role === 'supervisor';
            if ($esCerrado && !$esSupervisor) {
                return back()->withErrors(['estado_id' => 'Este registro esta cerrado. Solo supervisor puede reabrirlo.'])->withInput();
            }
        }

        $estadoPendienteId = Estado::where('slug', 'pendiente-aprobacion-supervisor')->value('id');
        $estadoGestion = $data['estado_id'] ? Estado::find($data['estado_id']) : null;
        $esCierre = $estadoGestion?->slug === 'cerrado';
        if ($esCierre) {
            $request->validate([
                'efectivo' => ['required', 'in:SI,NO'],
                'monto_linea_credito' => ['required', 'numeric', 'min:0'],
            ]);
            $data['estado_id'] = $estadoPendienteId;
        }

        $gestion = Gestion::create($data);

        if ($gestion->base_asignada_id) {
            $update = ['estado_id' => $gestion->estado_id];
            if ($request->filled('linea_credito')) {
                $update['linea_credito'] = $request->input('linea_credito');
            }

            if ($esCierre) {
                $update['efectivo'] = $request->input('efectivo') === 'SI';
                $update['monto_linea_credito'] = $request->input('monto_linea_credito');
                $update['cierre_solicitado_at'] = now();
                $update['cierre_solicitado_por'] = auth()->id();
                $update['motivo_devolucion'] = null;
            }

            BaseAsignada::whereKey($gestion->base_asignada_id)->update($update);
            $msg = $esCierre
                ? 'Gestion enviada a aprobacion del supervisor.'
                : 'Gestion registrada.';
            return redirect()->route('base-asignada.show', $gestion->base_asignada_id)->with('ok', $msg);
        }

        ClientePotencial::whereKey($gestion->cliente_potencial_id)->update(['estado_id' => $gestion->estado_id]);
        return redirect()->route('clientes-potenciales.show', $gestion->cliente_potencial_id)->with('ok', 'Gestion registrada.');
    }
}
