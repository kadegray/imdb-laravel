<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbTitle extends Model
{
    public $timestamps = false;

    public $fillable = [
        'tconst',
        'title_type',
        'primary_title',
        'original_title',
        'start_year',
        'end_year',
        'runtime_minutes',
        'genres',
        'average_rating',
        'num_votes',
    ];

    public function genres2()
    {
        return $this->belongsToMany(ImdbGenre::class, 'imdb_genre_imdb_title', 'imdb_title_id', 'imdb_genre_id');
    }
}
