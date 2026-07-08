<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduzioneCiclo extends Model
{
    protected $table = 'produzioni_ciclo';
    public $timestamps = false;

    protected $fillable = [
        'produzione_id',
        'flusso_id',
        'nome',
        'registrazione_1',
        'registrazione_2',
        'controllo',
        'ordine',
    ];

    protected $casts = [
        'controllo' => 'boolean',
        'ordine'    => 'integer',
    ];

    public function flusso()
    {
        return $this->belongsTo(FlussoProduzione::class, 'flusso_id');
    }
}
