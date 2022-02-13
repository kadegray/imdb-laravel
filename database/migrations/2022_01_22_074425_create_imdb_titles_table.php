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
        Schema::create('imdb_titles', function (Blueprint $table) {
            $table->id();
            $table->string('tconst');
            $table->string('title_type')->nullable();
            $table->text('primary_title')->nullable();
            $table->text('original_title')->nullable();
            $table->string('start_year')->nullable();
            $table->string('end_year')->nullable();
            $table->string('runtime_minutes')->nullable();
            $table->string('parent_tconst')->nullable();
            $table->string('season_number')->nullable();
            $table->string('episode_number')->nullable();
            $table->string('genres')->nullable();
            $table->string('average_rating')->nullable();
            $table->string('num_votes')->nullable();
        });

        Schema::table('imdb_titles', function (Blueprint $table) {
            $table->index('tconst');
            $table->index('title_type');
            $table->index('primary_title');
            $table->index('start_year');
            $table->index('end_year');
            $table->index('runtime_minutes');
            $table->index('season_number');
            $table->index('episode_number');
            $table->index('genres');
            $table->index('average_rating');
            $table->index('num_votes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('imdb_titles');
    }
};
