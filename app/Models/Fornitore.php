<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fornitore extends Model
{
    protected $table = 'fornitori';

    protected $fillable = [
        'codice', 'ragione_sociale', 'tipo', 'piva', 'indirizzo',
        'email', 'telefono', 'haccp_certificato', 'haccp_scadenza',
        'certificazioni_note', 'moca_certificato', 'moca_numero',
        'attivo', 'note',
    ];

    protected $casts = [
        'haccp_certificato' => 'boolean',
        'haccp_scadenza' => 'date',
        'moca_certificato' => 'boolean',
        'attivo' => 'boolean',
    ];
}
