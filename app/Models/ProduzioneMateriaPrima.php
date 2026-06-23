<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduzioneMateriaPrima extends Model
{
    protected $table = 'produzioni_materie_prime';
    public $timestamps = false;

    protected $fillable = [
        'produzione_id',
        'acquisto_riga_id',
        'semilavorato_id',
        'materia_prima_id',
        'quantita_kg',
    ];

    protected $casts = [
        'quantita_kg' => 'decimal:3',
    ];

    public function acquistoRiga()
    {
        return $this->belongsTo(AcquistoRiga::class);
    }

    public function semilavorato()
    {
        return $this->belongsTo(LottoSemilavorato::class);
    }

    public function materiaPrima()
    {
        return $this->belongsTo(MateriaPrima::class);
    }
}
