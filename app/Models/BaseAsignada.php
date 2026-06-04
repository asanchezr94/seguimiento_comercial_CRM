<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaseAsignada extends Model
{
    protected $table = 'base_asignadas';

    protected $fillable = [
        'supervisor_id',
        'lote_uid',
        'lote_nombre',
        'asesor_id',
        'asignado_at',
        'estado_id',
        'persona_id',
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
        'motivo_devolucion',
        'telefono',
        'email',
        'empresa',
        'origen',
        'observaciones',
        'ultima_gestion_at',
        'cierre_aprobado_at',
    ];

    protected $casts = [
        'asignado_at' => 'datetime',
        'ultima_gestion_at' => 'datetime',
        'cierre_aprobado_at' => 'datetime',
        'desembolso_solicitado_at' => 'datetime',
        'desembolso_aprobado_at' => 'datetime',
    ];

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function gestiones(): HasMany
    {
        return $this->hasMany(Gestion::class);
    }
}
