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
        'lote_nombre',
        'asesor_id',
        'estado_id',
        'nombre',
        'cedula',
        'linea_credito',
        'efectivo',
        'monto_linea_credito',
        'motivo_devolucion',
        'telefono',
        'email',
        'empresa',
        'origen',
        'observaciones',
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

    public function gestiones(): HasMany
    {
        return $this->hasMany(Gestion::class);
    }
}
