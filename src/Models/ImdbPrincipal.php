<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbPrincipal extends Model
{
    public $timestamps = false;

    public $fillable = [
        'tconst',
        'ordering',
        'nconst',
        'category',
        'job',
        'characters',
    ];

    public function title()
    {
        return $this->hasOne(ImdbTitle::class, 'tconst', 'tconst');
    }

    public function characters()
    {
        return $this->belongsToMany(ImdbCharacter::class, 'imdb_character_imdb_principal', 'imdb_principal_id', 'imdb_character_id');
    }
}
