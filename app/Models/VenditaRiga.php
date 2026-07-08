<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenditaRiga extends Model
{
    protected $table = 'vendite_righe';

    protected $fillable = [
        'vendita_id', 'prodotto_id', 'prodotto_variante_id', 'nome_prodotto', 'pezzatura_gr',
        'um', 'quantita_pz', 'quantita_kg', 'lotto', 'lotto_esterno', 'scadenza',
        'produzione_id', 'acquisto_riga_id',
        'codice_articolo', 'prezzo_unitario', 'sconto_1', 'sconto_2',
        'aliquota_iva', 'importo_netto',
    ];

    protected $casts = [
        'scadenza'        => 'date',
        'quantita_kg'     => 'decimal:3',
        'quantita_pz'     => 'decimal:3',
        'pezzatura_gr'    => 'decimal:3',
        'prezzo_unitario' => 'decimal:4',
        'sconto_1'        => 'decimal:2',
        'sconto_2'        => 'decimal:2',
        'aliquota_iva'    => 'decimal:2',
        'importo_netto'   => 'decimal:2',
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

    public function variante()
    {
        return $this->belongsTo(ProdottoVariante::class, 'prodotto_variante_id');
    }

    public function bolleReso()
    {
        return $this->hasMany(BollaReso::class, 'vendita_riga_id');
    }
}
