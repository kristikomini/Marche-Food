<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenditaRiga extends Model
{
    protected $table = 'vendite_righe';

    protected $fillable = [
        'vendita_id', 'prodotto_id', 'nome_prodotto', 'pezzatura_gr',
        'um', 'quantita_pz', 'quantita_kg', 'lotto', 'lotto_esterno', 'scadenza',
        'produzione_id', 'acquisto_riga_id',
    ];

    protected $casts = [
        'scadenza'     => 'date',
        'quantita_kg'  => 'decimal:3',
        'quantita_pz'  => 'decimal:3',
        'pezzatura_gr' => 'decimal:3',
    ];

    public function vendita()
    {
        return $this->belongsTo(Vendita::class);
    }

    public function produzione()
    {
        return $this->belongsTo(Produzione::class);
    }

    public function acquistoRiga()
    {
        return $this->belongsTo(AcquistoRiga::class);
    }

    public function bolleReso()
    {
        return $this->hasMany(BollaReso::class, 'vendita_riga_id');
    }
}
