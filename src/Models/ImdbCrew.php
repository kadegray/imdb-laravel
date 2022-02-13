<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbCrew extends Model
{
    protected $table = 'imdb_crew';
    public $timestamps = false;

    public $fillable = [
        'tconst',
        'directors',
        'writers',
    ];

    public function directors()
    {
        return $this->belongsToMany(ImdbDirector::class, 'imdb_crew_imdb_director', 'imdb_director_id', 'imdb_crew_id');
    }

    public function writers()
    {
        return $this->belongsToMany(ImdbWriter::class, 'imdb_crew_imdb_writer', 'imdb_writer_id', 'imdb_crew_id');
    }
}
