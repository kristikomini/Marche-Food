<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinazioneIngrediente extends Model
{
    protected $table = 'destinazione_ingredienti';

    public $timestamps = false;

    protected $fillable = ['prodotto_id', 'materia_prima_id'];

    public function prodotto()
    {
        return $this->belongsTo(Prodotto::class);
    }

    public function materiaPrima()
    {
        return $this->belongsTo(MateriaPrima::class);
    }
}
