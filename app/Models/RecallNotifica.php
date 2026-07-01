<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecallNotifica extends Model
{
    protected $table = 'recall_notifiche';

    protected $fillable = [
        'recall_id', 'cliente_id', 'vendita_riga_id',
        'documento', 'quantita_kg', 'notificato', 'notificato_at', 'note',
    ];

    protected $casts = [
        'notificato'    => 'boolean',
        'notificato_at' => 'datetime',
        'quantita_kg'   => 'decimal:3',
    ];

    public function recall(): BelongsTo
    {
        return $this->belongsTo(Recall::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venditaRiga(): BelongsTo
    {
        return $this->belongsTo(VenditaRiga::class);
    }
}
