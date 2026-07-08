<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produzione extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'produzioni';

    protected $fillable = [
        'scheda_id',
        'lotto_produzione',
        'data_produzione',
        'quantita_prodotta_kg',
        'operatore',
        'note',
    ];

    protected $casts = [
        'data_produzione'     => 'date',
        'quantita_prodotta_kg' => 'decimal:3',
    ];

    public function scheda()
    {
        return $this->belongsTo(SchedaProduzione::class);
    }

    public function materiePrime()
    {
        return $this->hasMany(ProduzioneMateriaPrima::class);
    }

    public function imballaggiPrimari()
    {
        return $this->hasMany(ProduzioneImballaggioPrimario::class);
    }

    public function detergenti()
    {
        return $this->hasMany(ProduzioneDetergente::class);
    }

    public function lottoSemilavorato()
    {
        return $this->hasOne(LottoSemilavorato::class);
    }

    public function confezioni()
    {
        return $this->hasMany(ProduzioneConfezione::class);
    }

    public function gas()
    {
        return $this->hasMany(ProduzioneGas::class);
    }

    public function ciclo()
    {
        return $this->hasMany(ProduzioneCiclo::class)->orderBy('ordine');
    }

    public function metalDetector()
    {
        return $this->hasOne(ProduzioneMetalDetector::class);
    }
}
