<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbDirector extends Model
{
    public $timestamps = false;

    public $fillable = [
        'name',
    ];

    public function crew()
    {
        return $this->belongsToMany(ImdbCrew::class, 'imdb_crew_imdb_director', 'imdb_crew_id', 'imdb_director_id');
    }
}
