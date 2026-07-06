<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Acquisto extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'acquisti';

    protected $fillable = [
        'fornitore_id',
        'numero_documento',
        'data_documento',
        'tipo_documento',
        'note',
        'is_conto_terzi',
    ];

    protected $casts = [
        'data_documento'  => 'date',
        'is_conto_terzi'  => 'boolean',
    ];

    public function fornitore()
    {
        return $this->belongsTo(Fornitore::class);
    }

    public function righe()
    {
        return $this->hasMany(AcquistoRiga::class);
    }
}
