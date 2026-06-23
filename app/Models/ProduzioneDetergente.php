<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduzioneDetergente extends Model
{
    protected $table = 'produzioni_detergenti';

    protected $fillable = [
        'produzione_id',
        'lotto_detergente_id',
        'quantita_usata',
        'note',
    ];

    protected $casts = [
        'quantita_usata' => 'decimal:3',
    ];

    public function produzione()
    {
        return $this->belongsTo(Produzione::class);
    }

    public function lottoDetergente()
    {
        return $this->belongsTo(LottoDetergente::class, 'lotto_detergente_id');
    }
}
