<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduzioneConfezione extends Model
{
    protected $table = 'produzioni_confezioni';
    public $timestamps = false;

    protected $fillable = [
        'produzione_id',
        'prodotto_variante_id',
        'n_confezioni',
    ];

    protected $casts = [
        'n_confezioni' => 'integer',
    ];

    public function variante()
    {
        return $this->belongsTo(ProdottoVariante::class, 'prodotto_variante_id');
    }
}
