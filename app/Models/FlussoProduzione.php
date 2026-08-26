<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlussoProduzione extends Model
{
    protected $table = 'flussi_produzione';

    public $timestamps = false;

    protected $fillable = ['numero', 'nome', 'controllo', 'misura'];
}
