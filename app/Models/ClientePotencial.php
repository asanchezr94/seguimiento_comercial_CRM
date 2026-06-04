<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientePotencial extends Model
{
    protected $fillable = [
        'asesor_id',
        'lote_nombre',
        'estado_id',
        'nombre',
        'cedula',
        'linea_credito',
        'monto_solicitado',
        'efectivo',
        'desembolso_estado',
        'desembolso_estado_pendiente',
        'desembolso_solicitado_at',
        'desembolso_solicitado_por',
        'desembolso_aprobado_at',
        'desembolso_motivo_devolucion',
        'monto_linea_credito',
        'cierre_solicitado_at',
        'cierre_aprobado_at',
        'cierre_solicitado_por',
        'motivo_devolucion',
        'ultima_gestion_at',
        'telefono',
        'email',
        'empresa',
        'fuente',
        'observaciones',
    ];

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function gestiones(): HasMany
    {
        return $this->hasMany(Gestion::class);
    }

    protected function casts(): array
    {
        return [
            'efectivo' => 'boolean',
            'cierre_solicitado_at' => 'datetime',
            'cierre_aprobado_at' => 'datetime',
            'ultima_gestion_at' => 'datetime',
            'desembolso_solicitado_at' => 'datetime',
            'desembolso_aprobado_at' => 'datetime',
        ];
    }
}
