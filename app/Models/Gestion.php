<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gestion extends Model
{
    protected $fillable = [
        'asesor_id',
        'estado_id',
        'base_asignada_id',
        'cliente_potencial_id',
        'tipo',
        'detalle',
        'proxima_gestion_at',
    ];

    protected function casts(): array
    {
        return ['proxima_gestion_at' => 'datetime'];
    }

    public function baseAsignada(): BelongsTo
    {
        return $this->belongsTo(BaseAsignada::class);
    }

    public function clientePotencial(): BelongsTo
    {
        return $this->belongsTo(ClientePotencial::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }
}
