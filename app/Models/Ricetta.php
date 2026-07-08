<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ricetta extends Model
{
    protected $table = 'ricette';
    public $timestamps = false;

    protected $fillable = [
        'scheda_id',
        'materia_prima_id',
        'fornitore_id',
        'percentuale',
        'grammi_per_kg',
        'um',
        'ordine',
    ];

    protected $casts = [
        'percentuale'  => 'decimal:3',
        'grammi_per_kg' => 'decimal:3',
    ];

    public function materiaPrima()
    {
        return $this->belongsTo(MateriaPrima::class);
    }

    public function fornitore()
    {
        return $this->belongsTo(Fornitore::class);
    }
}
