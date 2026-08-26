<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RicettaMarinatura extends Model
{
    protected $table = 'ricette_marinature';

    public $timestamps = false;

    protected $fillable = [
        'scheda_id',
        'materia_prima_id',
        'fornitore_id',
        'litri_grammi',
        'um',
        'ordine',
    ];

    protected $casts = [
        'litri_grammi' => 'decimal:3',
    ];

    public function materiaPrima()
    {
        return $this->belongsTo(MateriaPrima::class);
    }
}
