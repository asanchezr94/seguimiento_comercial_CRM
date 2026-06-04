<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VisitaController extends Controller
{
    private function isSupervisor(): bool
    {
        return auth()->user()?->role === 'supervisor';
    }

    private function authorizeVisita(Visita $visita): void
    {
        if ($this->isSupervisor()) {
            return;
        }

        abort_unless($visita->user_id === auth()->id(), 403);
    }

    public function index(Request $request)
    {
        $mes = max(1, min(12, (int) $request->input('mes', now()->month)));
        $anio = (int) $request->input('anio', now()->year);
        if ($anio < 2026 || $anio > 2036) {
            $anio = max(2026, min(2036, (int) now()->year));
        }

        $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes = (clone $inicioMes)->endOfMonth();
        $inicioCalendario = (clone $inicioMes)->startOfWeek(Carbon::MONDAY);
        $finCalendario = (clone $finMes)->endOfWeek(Carbon::SUNDAY);

        $query = Visita::with('asesor')
            ->whereBetween('programada_at', [$inicioCalendario, $finCalendario])
            ->orderBy('programada_at');

        $visitas = $query->get();
        $visitasPorDia = $visitas->groupBy(fn (Visita $v) => $v->programada_at->format('Y-m-d'));
        $asesores = User::whereIn('role', ['comercial', 'supervisor'])->orderBy('name')->get();

        return view('visitas.index', compact(
            'mes',
            'anio',
            'inicioMes',
            'inicioCalendario',
            'finCalendario',
            'visitasPorDia',
            'asesores'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'programada_at' => ['required', 'date'],
            'finaliza_at' => ['required', 'date', 'after_or_equal:programada_at'],
        ]);

        if (!$this->isSupervisor()) {
            $data['user_id'] = auth()->id();
        } elseif (empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        $data['estado'] = 'programada';
        Visita::create($data);

        $fecha = Carbon::parse($data['programada_at']);
        return redirect()
            ->route('visitas.index', ['mes' => $fecha->month, 'anio' => $fecha->year])
            ->with('ok', 'Visita programada.');
    }

    public function show(string $id)
    {
        $visita = Visita::with('asesor')->find($id);
        if (!$visita) {
            return redirect()->route('visitas.index')->withErrors(['visita' => 'La visita consultada ya no existe o no esta disponible.']);
        }

        $puedeRegistrar = $this->isSupervisor() || $visita->user_id === auth()->id();

        return view('visitas.show', compact('visita', 'puedeRegistrar'));
    }

    public function registrar(Request $request, string $id)
    {
        $visita = Visita::findOrFail($id);
        $this->authorizeVisita($visita);

        $data = $request->validate([
            'estado' => ['required', 'in:realizada,cancelada'],
            'resultado' => ['required', 'string', 'min:3'],
        ]);

        $visita->update([
            'estado' => $data['estado'],
            'resultado' => $data['resultado'],
            'registrada_at' => now(),
        ]);

        return redirect()
            ->route('visitas.index', ['mes' => $visita->programada_at->month, 'anio' => $visita->programada_at->year])
            ->with('ok', 'Resultado de visita registrado.');
    }
}
