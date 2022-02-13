<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbGenre extends Model
{
    public $timestamps = false;

    public $fillable = [
        'name',
    ];

    public function movies()
    {
        return $this->belongsToMany(ImdbTitle::class, 'imdb_genre_imdb_title', 'imdb_genre_id', 'imdb_title_id');
    }
}
