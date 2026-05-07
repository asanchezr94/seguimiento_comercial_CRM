<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    protected $fillable = [
        'cedula',
        'nombre',
        'telefono',
        'email',
    ];

    public function basesAsignadas(): HasMany
    {
        return $this->hasMany(BaseAsignada::class);
    }
}
