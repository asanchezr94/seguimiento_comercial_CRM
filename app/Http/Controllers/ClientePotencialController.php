<?php

namespace App\Http\Controllers;

use App\Models\ClientePotencial;
use App\Models\Estado;
use Illuminate\Http\Request;

class ClientePotencialController extends Controller
{
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

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = ClientePotencial::with('estado')->latest();
        if (!$this->isSupervisor()) {
            $query->where('asesor_id', auth()->id());
        }
        $clientes = $query->paginate(20)->withQueryString();
        return view('clientes_potenciales.index', compact('clientes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        return view('clientes_potenciales.create', compact('estados'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'fuente' => ['nullable', 'string', 'max:255'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if (!$this->isSupervisor()) {
            $data['asesor_id'] = auth()->id();
        } elseif (empty($data['asesor_id'])) {
            $data['asesor_id'] = auth()->id();
        }

        ClientePotencial::create($data);
        return redirect()->route('clientes-potenciales.index')->with('ok', 'Registro creado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cliente = ClientePotencial::with(['estado', 'gestiones.estado'])->findOrFail($id);
        $this->authorizeCliente($cliente);
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        return view('clientes_potenciales.show', compact('cliente', 'estados'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $cliente = ClientePotencial::findOrFail($id);
        $this->authorizeCliente($cliente);
        $estados = Estado::where('activo', true)->orderBy('nombre')->get();
        return view('clientes_potenciales.edit', compact('cliente', 'estados'));
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
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'fuente' => ['nullable', 'string', 'max:255'],
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
}
