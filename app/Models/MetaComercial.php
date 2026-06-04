<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaComercial extends Model
{
    protected $table = 'metas_comerciales';

    protected $fillable = [
        'user_id',
        'mes',
        'anio',
        'monto_meta',
    ];

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

