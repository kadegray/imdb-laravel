<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbProfession extends Model
{
    public $timestamps = false;

    public $fillable = [
        'name',
    ];

    public function names()
    {
        return $this->belongsToMany(ImdbName::class, 'imdb_name_imdb_profession', 'imdb_name_id', 'imdb_profession_id');
    }
}
