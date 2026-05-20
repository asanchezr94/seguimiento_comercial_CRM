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
        'minutos_invertidos',
    ];

    protected function casts(): array
    {
        return [
            'proxima_gestion_at' => 'datetime',
            'minutos_invertidos' => 'integer',
        ];
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

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }
}
