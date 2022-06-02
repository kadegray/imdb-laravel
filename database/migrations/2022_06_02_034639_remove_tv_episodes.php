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
        Schema::table('imdb_titles', function (Blueprint $table) {
            $table->dropColumn('parent_tconst');
            $table->dropColumn('season_number');
            $table->dropColumn('episode_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('imdb_titles', function (Blueprint $table) {
            $table->string('parent_tconst')->nullable()->after('runtime_minutes');
            $table->string('season_number')->nullable()->after('parent_tconst');
            $table->string('episode_number')->nullable()->after('season_number');
        });
    }
};
