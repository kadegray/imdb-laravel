<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('imdb_genres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->index('name');
        });

        Schema::create('imdb_genre_imdb_title', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imdb_title_id');
            $table->unsignedBigInteger('imdb_genre_id');
            $table->index(['imdb_title_id', 'imdb_genre_id'], 'imdb_title_id_imdb_genre_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('imdb_genre_imdb_title');
        Schema::dropIfExists('imdb_genres');
    }
};
