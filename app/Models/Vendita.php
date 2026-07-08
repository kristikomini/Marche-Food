<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendita extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'vendite';

    protected $fillable = [
        'cliente_id',
        'numero_documento',
        'data_documento',
        'tipo_documento',
        'condizioni_pagamento',
        'causale_trasporto',
        'note',
        'n_colli',
        'peso_totale',
        'data_trasporto',
        'destinatario_diverso',
    ];

    protected $casts = [
        'data_documento' => 'date',
        'data_trasporto' => 'date',
        'peso_totale'    => 'decimal:3',
        'n_colli'        => 'integer',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function righe()
    {
        return $this->hasMany(VenditaRiga::class);
    }
}
