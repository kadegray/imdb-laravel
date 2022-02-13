<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbWriter extends Model
{
    public $timestamps = false;

    public $fillable = [
        'name',
    ];

    public function crew()
    {
        return $this->belongsToMany(ImdbCrew::class, 'imdb_crew_imdb_writer', 'imdb_crew_id', 'imdb_writer_id');
    }
}
