<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedaImballaggio extends Model
{
    protected $table = 'schede_imballaggi';
    public $timestamps = false;

    protected $fillable = [
        'scheda_id',
        'componente',
        'prodotto_variante_id',
        'fornitore_id',
        'ordine',
    ];

    public function fornitore()
    {
        return $this->belongsTo(Fornitore::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProdottoVariante::class, 'prodotto_variante_id');
    }
}
