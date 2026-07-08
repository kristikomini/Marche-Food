<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduzioneMetalDetector extends Model
{
    protected $table = 'produzioni_metal_detector';
    public $timestamps = false;

    protected $fillable = [
        'produzione_id',
        'inizio_conf',
        'fine_conf',
        'campione_1',
        'campione_2',
        'campione_3',
        'note',
    ];
}
