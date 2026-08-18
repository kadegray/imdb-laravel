<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Dedupe first: upsert()-based imports require tconst to be truly unique.
        DB::table('imdb_titles')
            ->select('tconst')
            ->groupBy('tconst')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('tconst')
            ->each(function ($tconst) {
                $minId = DB::table('imdb_titles')->where('tconst', $tconst)->min('id');

                DB::table('imdb_titles')
                    ->where('tconst', $tconst)
                    ->where('id', '>', $minId)
                    ->delete();
            });

        Schema::table('imdb_titles', function (Blueprint $table) {
            $table->dropIndex(['tconst']);
            $table->unique('tconst');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Note: rows deleted by the dedupe pass in up() are not restored.
        Schema::table('imdb_titles', function (Blueprint $table) {
            $table->dropUnique(['tconst']);
            $table->index('tconst');
        });
    }
};
