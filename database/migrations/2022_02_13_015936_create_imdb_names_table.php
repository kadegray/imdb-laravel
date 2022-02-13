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
        Schema::create('imdb_names', function (Blueprint $table) {
            $table->id();
            $table->string('nconst');
            $table->string('primary_name')->nullable();
            $table->integer('birth_year')->nullable();
            $table->integer('death_year')->nullable();
            $table->string('primary_profession')->nullable();
            $table->string('known_for_titles')->nullable();
        });

        Schema::table('imdb_names', function (Blueprint $table) {
            $table->index('nconst');
            $table->index('primary_name');
            $table->index('birth_year');
            $table->index('death_year');
            $table->index('primary_profession');
            $table->index('known_for_titles');
        });

        Schema::create('imdb_professions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->index('name');
        });

        Schema::create('imdb_name_imdb_profession', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imdb_name_id');
            $table->unsignedBigInteger('imdb_profession_id');
            $table->index(['imdb_name_id', 'imdb_profession_id'], 'imdb_name_id_imdb_profession_id');
        });

        Schema::create('imdb_name_known_for_imdb_title', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imdb_name_id');
            $table->unsignedBigInteger('imdb_title_id');
            $table->index(['imdb_name_id', 'imdb_title_id'], 'imdb_name_id_imdb_title_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('imdb_name_known_for_imdb_title');
        Schema::dropIfExists('imdb_name_imdb_profession');
        Schema::dropIfExists('imdb_professions');
        Schema::dropIfExists('imdb_names');
    }
};
