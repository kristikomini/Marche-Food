<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitaMisura extends Model
{
    protected $table = 'unita_misura';

    public $timestamps = false;

    protected $fillable = ['codice', 'descrizione', 'tipo'];
}
