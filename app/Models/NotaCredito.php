<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotaCredito extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'note_credito';

    protected $fillable = [
        'vendita_id', 'bolla_reso_id', 'numero_documento', 'data_documento', 'importo', 'note',
    ];

    protected $casts = [
        'data_documento' => 'date',
        'importo'        => 'decimal:2',
    ];

    public function vendita()
    {
        return $this->belongsTo(Vendita::class);
    }

    public function bollaReso()
    {
        return $this->belongsTo(BollaReso::class, 'bolla_reso_id');
    }
}
