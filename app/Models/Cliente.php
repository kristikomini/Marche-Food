<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clienti';

    protected $fillable = [
        'codice_cliente',
        'ragione_sociale',
        'piva',
        'indirizzo',
        'email',
        'telefono',
        'attivo',
        'note',
    ];

    protected $casts = [
        'attivo' => 'boolean',
    ];

    public function vendite()
    {
        return $this->hasMany(Vendita::class);
    }
}
