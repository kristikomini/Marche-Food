<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedaGas extends Model
{
    protected $table = 'schede_gas';
    public $timestamps = false;

    protected $fillable = [
        'scheda_id',
        'nome',
        'fornitore_id',
        'ordine',
    ];

    public function fornitore()
    {
        return $this->belongsTo(Fornitore::class);
    }
}
