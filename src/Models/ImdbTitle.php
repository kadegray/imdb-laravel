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
        'parent_tconst',
        'season_number',
        'episode_number',
        'genres',
        'average_rating',
        'num_votes',
    ];

    public function imdbGenres()
    {
        return $this->belongsToMany(ImdbGenre::class, 'imdb_genre_imdb_title', 'imdb_title_id', 'imdb_genre_id');
    }

    // public function namesKnownForThisTitle()
    // {
    //     return $this->belongsToMany(ImdbName::class, 'imdb_name_known_for_imdb_title', 'imdb_title_id', 'imdb_name_id');
    // }

    // public function principals()
    // {
    //     return $this->hasMany(ImdbPrincipal::class, 'tconst', 'tconst');
    // }
}
