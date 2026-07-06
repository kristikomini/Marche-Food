<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LottoDetergente extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'lotti_detergenti';

    protected $fillable = [
        'fornitore_id',
        'codice_articolo',
        'componente',
        'um',
        'quantita',
        'lotto',
        'scadenza',
        'numero_ddt',
        'data_in',
        'data_out',
        'note',
    ];

    protected $casts = [
        'data_in'  => 'date',
        'data_out' => 'date',
        'scadenza' => 'date',
        'quantita' => 'decimal:3',
    ];

    public function fornitore()
    {
        return $this->belongsTo(Fornitore::class);
    }
}
