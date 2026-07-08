<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdottoVariante extends Model
{
    protected $table = 'prodotto_varianti';

    protected $fillable = [
        'prodotto_id',
        'codice_prodotto',
        'pezzatura_valore',
        'pezzatura_um',
        'um_id',
        'descrizione',
        'ordine',
        'attiva',
    ];

    protected $casts = [
        'pezzatura_valore' => 'decimal:3',
        'attiva'           => 'boolean',
        'ordine'           => 'integer',
    ];

    public function prodotto()
    {
        return $this->belongsTo(Prodotto::class);
    }

    public function unitaMisura()
    {
        return $this->belongsTo(UnitaMisura::class, 'um_id');
    }

    /** Etichetta pezzatura formattata, es. "gr 200" o "kg 1". */
    public function getPezzaturaLabelAttribute(): ?string
    {
        if ($this->pezzatura_valore === null) {
            return $this->pezzatura_um ?: null;
        }
        $val = rtrim(rtrim(number_format((float) $this->pezzatura_valore, 3, ',', '.'), '0'), ',');

        return trim(($this->pezzatura_um ? $this->pezzatura_um . ' ' : '') . $val);
    }
}
