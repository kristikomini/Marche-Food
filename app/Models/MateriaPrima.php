<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MateriaPrima extends Model
{
    protected $table = 'materie_prime';

    protected $fillable = [
        'codice',
        'nome',
        'um_id',
        'allergeni',
        'allergeni_tracce',
    ];

    protected $casts = [
        'allergeni'        => 'array',
        'allergeni_tracce' => 'array',
    ];

    public function unitaMisura()
    {
        return $this->belongsTo(UnitaMisura::class, 'um_id');
    }
}
