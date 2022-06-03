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
        Schema::dropIfExists('imdb_character_imdb_principal');
        Schema::dropIfExists('imdb_characters');
        Schema::dropIfExists('imdb_principals');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('imdb_principals', function (Blueprint $table) {
            $table->id();
            $table->string('tconst');
            $table->string('ordering')->nullable();
            $table->string('nconst')->nullable();
            $table->string('category')->nullable();
            $table->string('job')->nullable();
            $table->string('characters')->nullable();
        });

        Schema::table('imdb_principals', function (Blueprint $table) {
            $table->index('tconst');
            $table->index('ordering');
            $table->index('nconst');
            $table->index('category');
            $table->index('job');
            $table->index('characters');
        });

        Schema::create('imdb_characters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->index('name');
        });

        Schema::create('imdb_character_imdb_principal', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imdb_principal_id');
            $table->unsignedBigInteger('imdb_character_id');
            $table->index(['imdb_principal_id', 'imdb_character_id'], 'imdb_principal_id_imdb_character_id');
        });
    }
};
