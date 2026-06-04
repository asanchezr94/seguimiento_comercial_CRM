<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visita extends Model
{
    protected $fillable = [
        'user_id',
        'titulo',
        'cliente_nombre',
        'telefono',
        'direccion',
        'programada_at',
        'finaliza_at',
        'estado',
        'resultado',
        'registrada_at',
    ];

    protected function casts(): array
    {
        return [
            'programada_at' => 'datetime',
            'finaliza_at' => 'datetime',
            'registrada_at' => 'datetime',
        ];
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
