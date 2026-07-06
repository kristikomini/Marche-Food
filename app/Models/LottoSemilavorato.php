<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LottoSemilavorato extends Model
{
    protected $table = 'lotti_semilavorati';

    protected $fillable = [
        'produzione_id',
        'lotto',
        'nome_prodotto',
        'quantita_kg',
        'data_produzione',
        'data_out',
        'note',
    ];

    protected $casts = [
        'quantita_kg'     => 'decimal:3',
        'data_produzione' => 'date',
        'data_out'        => 'date',
    ];

    public function produzione(): BelongsTo
    {
        return $this->belongsTo(Produzione::class);
    }

    public function materiePrimeConsumi(): HasMany
    {
        return $this->hasMany(ProduzioneMateriaPrima::class, 'semilavorato_id');
    }
}
