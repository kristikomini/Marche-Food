<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcquistoRiga extends Model
{
    protected $table = 'acquisti_righe';

    protected $fillable = [
        'acquisto_id',
        'prodotto_id',
        'materia_prima_id',
        'nome_prodotto',
        'um',
        'quantita_pz',
        'quantita_kg',
        'lotto',
        'lotto_esterno',
        'scadenza',
        'data_in',
        'data_out',
        'nota_credito_ref',
    ];

    protected $casts = [
        'scadenza'     => 'date',
        'data_in'      => 'date',
        'data_out'     => 'date',
        'quantita_kg'  => 'decimal:3',
        'quantita_pz'  => 'decimal:3',
    ];

    public function acquisto()
    {
        return $this->belongsTo(Acquisto::class);
    }

    public function materiaPrima()
    {
        return $this->belongsTo(MateriaPrima::class);
    }

    public function produzioniMateriePrime()
    {
        return $this->hasMany(ProduzioneMateriaPrima::class);
    }
}
