<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Acquisto extends Model
{
    use Auditable;

    protected $table = 'acquisti';

    protected $fillable = [
        'fornitore_id',
        'numero_documento',
        'data_documento',
        'tipo_documento',
        'note',
    ];

    protected $casts = [
        'data_documento' => 'date',
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
