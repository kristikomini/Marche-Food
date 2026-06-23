<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduzioneImballaggioPrimario extends Model
{
    protected $table = 'produzioni_imballaggi_primari';

    protected $fillable = [
        'produzione_id',
        'lotto_imballaggio_id',
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

    public function lottoImballaggio()
    {
        return $this->belongsTo(LottoImballaggioPrimario::class, 'lotto_imballaggio_id');
    }
}
