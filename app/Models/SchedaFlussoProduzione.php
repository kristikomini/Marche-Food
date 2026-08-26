<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedaFlussoProduzione extends Model
{
    protected $table = 'schede_produzione_flussi';

    public $timestamps = false;

    protected $fillable = ['scheda_id', 'flusso_id', 'ordine', 'valore_controllo', 'tempo_minuti'];

    public function flusso()
    {
        return $this->belongsTo(FlussoProduzione::class, 'flusso_id');
    }
}
