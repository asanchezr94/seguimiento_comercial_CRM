<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientePotencial extends Model
{
    protected $fillable = [
        'asesor_id',
        'estado_id',
        'nombre',
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
}
