<?php

namespace KadeGray\ImdbLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class ImdbCharacter extends Model
{
    public $timestamps = false;

    public $fillable = [
        'name',
    ];

    public function principals()
    {
        return $this->belongsToMany(ImdbPrincipal::class, 'imdb_character_imdb_principal', 'imdb_character_id', 'imdb_principal_id');
    }
}
