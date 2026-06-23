<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class BollaReso extends Model
{
    use Auditable;

    protected $table = 'bolle_reso';

    protected $fillable = [
        'vendita_riga_id', 'numero_bolla', 'quantita_pz', 'quantita_kg', 'data_reso', 'note',
    ];

    protected $casts = [
        'data_reso'   => 'date',
        'quantita_kg' => 'decimal:3',
        'quantita_pz' => 'decimal:3',
    ];

    public function venditaRiga()
    {
        return $this->belongsTo(VenditaRiga::class, 'vendita_riga_id');
    }

    public function notaCredito()
    {
        return $this->hasOne(NotaCredito::class, 'bolla_reso_id');
    }
}
