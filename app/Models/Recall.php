<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recall extends Model
{
    use Auditable;

    protected $fillable = [
        'lotto', 'prodotto', 'motivo', 'stato',
        'data_apertura', 'data_chiusura', 'note',
    ];

    protected $casts = [
        'data_apertura' => 'date',
        'data_chiusura' => 'date',
    ];

    public function notifiche(): HasMany
    {
        return $this->hasMany(RecallNotifica::class);
    }

    /** Number of customers still to be notified. */
    public function getDaNotificareAttribute(): int
    {
        return $this->notifiche->where('notificato', false)->count();
    }
}
