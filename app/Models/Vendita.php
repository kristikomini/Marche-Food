<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Vendita extends Model
{
    use Auditable;

    protected $table = 'vendite';

    protected $fillable = [
        'cliente_id',
        'numero_documento',
        'data_documento',
        'tipo_documento',
        'note',
    ];

    protected $casts = [
        'data_documento' => 'date',
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
