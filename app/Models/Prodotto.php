<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prodotto extends Model
{
    protected $table = 'prodotti';

    protected $fillable = [
        'nome',
        'attivo',
        'note',
    ];

    protected $casts = [
        'attivo' => 'boolean',
    ];

    public function varianti()
    {
        return $this->hasMany(ProdottoVariante::class)->orderBy('ordine')->orderBy('id');
    }

    public function schede()
    {
        return $this->hasMany(SchedaProduzione::class);
    }

    /**
     * Codice principale del prodotto = codice della prima variante (compat).
     * Utile per liste/ricerche dove serve "un" codice rappresentativo.
     */
    public function getCodicePrincipaleAttribute(): ?string
    {
        return $this->varianti->first()->codice_prodotto ?? null;
    }
}
