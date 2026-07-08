<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduzioneGas extends Model
{
    protected $table = 'produzioni_gas';
    public $timestamps = false;

    protected $fillable = [
        'produzione_id',
        'lotto_gas_id',
        'quantita_usata',
        'note',
    ];

    protected $casts = [
        'quantita_usata' => 'decimal:3',
    ];

    public function lottoGas()
    {
        return $this->belongsTo(LottoGas::class, 'lotto_gas_id');
    }
}
