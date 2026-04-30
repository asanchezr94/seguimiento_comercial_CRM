<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estado extends Model
{
    protected $fillable = ['nombre', 'slug', 'activo'];

    public function basesAsignadas(): HasMany
    {
        return $this->hasMany(BaseAsignada::class);
    }

    public function clientesPotenciales(): HasMany
    {
        return $this->hasMany(ClientePotencial::class);
    }
}
