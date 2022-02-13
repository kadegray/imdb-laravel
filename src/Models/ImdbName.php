<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbName extends Model
{
    public $timestamps = false;

    public $fillable = [
        'nconst',
        'primary_name',
        'birth_year',
        'death_year',
        'primary_profession',
        'known_for_titles',
    ];

    public function primaryProfessions()
    {
        return $this->belongsToMany(ImdbProfession::class, 'imdb_name_imdb_profession', 'imdb_profession_id', 'imdb_name_id');
    }

    public function knownForTitles()
    {
        return $this->belongsToMany(ImdbTitle::class, 'imdb_name_known_for_imdb_title', 'imdb_name_id', 'imdb_title_id');
    }
}
