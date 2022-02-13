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
        Schema::create('imdb_crew', function (Blueprint $table) {
            $table->id();
            $table->string('tconst');
            $table->string('directors')->nullable();
            $table->string('writers')->nullable();
        });

        Schema::table('imdb_crew', function (Blueprint $table) {
            $table->index('tconst');
            $table->index('directors');
            $table->index('writers');
        });


        Schema::create('imdb_directors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('imdb_crew_imdb_director', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imdb_crew_id');
            $table->unsignedBigInteger('imdb_director_id');
        });


        Schema::create('imdb_writers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('imdb_crew_imdb_writer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imdb_crew_id');
            $table->unsignedBigInteger('imdb_writer_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('imdb_crew_imdb_writer');
        Schema::dropIfExists('imdb_writers');
        Schema::dropIfExists('imdb_crew_imdb_director');
        Schema::dropIfExists('imdb_directors');
        Schema::dropIfExists('imdb_crew');
    }
};
