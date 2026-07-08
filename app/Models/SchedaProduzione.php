<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedaProduzione extends Model
{
    protected $table = 'schede_produzione';

    protected $fillable = [
        'prodotto_id',
        'modello',
        'revisione',
        'data_revisione',
        'ha_marinatura',
        'attiva',
        'note',
    ];

    protected $casts = [
        'data_revisione' => 'date',
        'ha_marinatura'  => 'boolean',
        'attiva'         => 'boolean',
    ];

    public function prodotto()
    {
        return $this->belongsTo(Prodotto::class);
    }

    public function ricette()
    {
        return $this->hasMany(Ricetta::class, 'scheda_id')->orderBy('ordine');
    }

    public function ricetteMarinature()
    {
        return $this->hasMany(RicettaMarinatura::class, 'scheda_id')->orderBy('ordine');
    }

    public function flussi()
    {
        return $this->hasMany(SchedaFlussoProduzione::class, 'scheda_id')->orderBy('ordine');
    }

    public function imballaggi()
    {
        return $this->hasMany(SchedaImballaggio::class, 'scheda_id')->orderBy('ordine');
    }

    public function gas()
    {
        return $this->hasMany(SchedaGas::class, 'scheda_id')->orderBy('ordine');
    }
}
