<?php

namespace KadeGray\ImdbLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KadeGray\ImdbLaravel\Models\ImdbGenre;
use KadeGray\ImdbLaravel\Models\ImdbTitle;
use KadeGray\ImdbLaravel\Models\ImdbName;
use KadeGray\ImdbLaravel\Models\ImdbProfession;
use KadeGray\ImdbLaravel\Models\ImdbPrincipal;
use KadeGray\ImdbLaravel\Models\ImdbCharacter;
use KadeGray\ImdbLaravel\Models\ImdbCrew;
use KadeGray\ImdbLaravel\Models\ImdbDirector;
use KadeGray\ImdbLaravel\Models\ImdbWriter;

class ImdbImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imdb:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import IMDB datasets.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->downloadFiles();

        $this->importTitles();
        $this->importTitleRatings();
        $this->importEpisodeData();

        $this->importNames();
        $this->importPrincipals();
        $this->importCrew();

        return 0;
    }

    public function importEpisodeData()
    {
        $imdbFileName = 'title.episode.tsv.gz';

        $this->newLine(2);
        $this->line("Importing TV Show Season and Episodes ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $tconst = data_get($row, '0');
            $imdbTitle = ImdbTitle::where('tconst', $tconst)->first();
            if (!$imdbTitle) {
                return;
            }

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue($row[$index]);

                if (in_array($heading, [
                    'parent_tconst',
                    'season_number',
                    'episode_number',
                ])) {
                    data_set($imdbTitle, $heading, $value);
                }
            }

            $imdbTitle->save();
        });
    }

    public function importCrew()
    {
        $imdbFileName = 'title.crew.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Crew ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $imdbCrewFields = (new ImdbCrew())->getFillable();
            $imdbCrew = [];

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue($row[$index]);

                if (in_array($heading, $imdbCrewFields)) {
                    data_set($imdbCrew, $heading, $value);
                }
            }

            $imdbCrew = ImdbCrew::create($imdbCrew);


            // directors
            $directors = Str::of($imdbCrew->directors)
                ->trim()
                ->explode(',')
                ->toArray();

            foreach ($directors as &$director) {
                $director = ImdbDirector::firstOrCreate([
                    'name' => $director,
                ])->id;
            }

            $imdbCrew->directors()->sync($directors);


            // writers
            $writers = Str::of($imdbCrew->writers)
                ->trim()
                ->explode(',')
                ->toArray();

            foreach ($writers as &$writer) {
                $writer = ImdbWriter::firstOrCreate([
                    'name' => $writer,
                ])->id;
            }

            $imdbCrew->writers()->sync($writers);
        });
    }

    public function importPrincipals()
    {
        $imdbFileName = 'title.principals.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Principals ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $imdbPrincipalFields = (new ImdbPrincipal())->getFillable();
            $imdbPrincipal = [];

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue($row[$index]);

                if (in_array($heading, $imdbPrincipalFields)) {
                    data_set($imdbPrincipal, $heading, $value);
                }
            }

            $imdbName = ImdbPrincipal::create($imdbPrincipal);


            $characters = $imdbName->characters;
            $characters = Str::replaceFirst('[', '', $characters);
            $characters = Str::replaceFirst(']', '', $characters);
            $characters = Str::of($characters)->explode(',')->toArray();
            foreach ($characters as &$character) {
                $character = Str::replaceFirst('"', '', $character);
                $character = Str::replaceFirst('"', '', $character);
            }

            foreach ($characters as &$character) {
                $character = ImdbCharacter::firstOrCreate([
                    'name' => $character,
                ]);
                $character = data_get($character, 'id');
            }

            $imdbName->characters()->sync($characters);
        });
    }

    public function importNames()
    {
        $imdbFileName = 'name.basics.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Names ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $imdbNameFields = (new ImdbName())->getFillable();
            $imdbName = [];

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue($row[$index]);

                if (in_array($heading, $imdbNameFields)) {
                    data_set($imdbName, $heading, $value);
                }
            }

            $imdbName = ImdbName::create($imdbName);


            // primary professions
            $primaryProfessions = Str::of($imdbName->primary_profession)
                ->trim()
                ->explode(',')
                ->toArray();

            foreach ($primaryProfessions as &$primaryProfession) {
                $primaryProfession = ImdbProfession::firstOrCreate([
                    'name' => $primaryProfession,
                ])->id;
            }

            $imdbName->primaryProfessions()->sync($primaryProfession);


            // known for titles
            $knownForTitles = Str::of($imdbName->known_for_titles)
                ->trim()
                ->explode(',')
                ->toArray();

            $knownForTitles = ImdbTitle::whereIn('tconst', $knownForTitles)
                ->get()
                ->pluck('id');

            $imdbName->knownForTitles()->sync($knownForTitles);
        });
    }

    public function importTitleRatings()
    {
        $imdbFileName = 'title.ratings.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Title Ratings ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $tconst = $row[0];
            $movie = ImdbTitle::where('tconst', $tconst)->first();
            if (!$movie) {

                return;
            }

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue($row[$index]);

                if (in_array($heading, [
                    'average_rating',
                    'num_votes',
                ])) {
                    data_set($movie, $heading, $value);
                }
            }

            $movie->save();
        });
    }

    public function importTitles()
    {
        $imdbFileName = 'title.basics.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Titles ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $movie = [];

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue($row[$index]);

                if (
                    $heading == 'is_adult'
                    && $value == '1'
                ) {
                    return;
                }

                if (
                    $heading == 'genres'
                    && Str::of($value)->contains('Adult')
                ) {
                    return;
                }

                data_set($movie, $heading, $value);
            }

            $imdbTitle = ImdbTitle::create($movie);

            $genres = Str::of($imdbTitle->genres)
                ->trim()
                ->explode(',')
                ->toArray();

            foreach ($genres as &$genre) {
                $genre = ImdbGenre::firstOrCreate([
                    'name' => $genre,
                ])->id;
            }

            $imdbTitle->genres2()->sync($genres);
        });
    }

    public function iterateOverFile($imdbFilename, $insertRow)
    {
        $filename = storage_path("app/$imdbFilename");
        $handle = gzopen($filename, "r");

        $headings = null;

        // $rowCount = 0;
        // $maxRows = 6000;

        $totalRows = $this->getLineCountOfDownload();
        $bar = $this->output->createProgressBar($totalRows);
        while (!gzeof($handle)) {
            $bar->advance();
            // $rowCount++;

            $row = gzgets($handle, 4096);
            $row = preg_split("/\t+/", $row);

            if (!$headings) {
                $headings = $row;
                continue;
            }

            $insertRow($headings, $row);

            // if ($rowCount >= $maxRows) {
            //     break;
            // }
        }

        gzclose($handle);
        $bar->finish();
    }

    public function getLineCountOfDownload($filename = 'title.basics.tsv.gz')
    {
        $filePath = storage_path("/app/$filename");
        $command = sprintf('cat %s | zcat | wc -l', $filePath);

        $lineCount = exec($command);

        return (int) $lineCount;
    }

    public function handleValue($value)
    {
        if (Str::of($value)->startsWith('\N')) {
            return null;
        }

        $value = Str::of($value)->trim();
        $value = Str::limit($value, 252);

        return $value;
    }

    public function downloadFiles()
    {
        foreach ([
            'title.basics.tsv.gz',
            'title.crew.tsv.gz',
            'title.episode.tsv.gz',
            'title.principals.tsv.gz',
            'title.ratings.tsv.gz',
            'name.basics.tsv.gz'
        ] as $imdbFilename) {

            if ($this->hasDownloadFile($imdbFilename)) {
                $this->info("$imdbFilename already downloaded");
                continue;
            }

            $this->downloadFile($imdbFilename);
        }
    }

    public function hasDownloadFile($imdbFilename = 'title.basics.tsv.gz')
    {
        return Storage::disk('local')->exists($imdbFilename);
    }

    public function downloadFile($imdbFilename = 'title.basics.tsv.gz')
    {
        $this->line("Downloading $imdbFilename");
        $titlesContents = file_get_contents("https://datasets.imdbws.com/$imdbFilename");
        $this->info("Downloaded $imdbFilename");

        $this->line("Saving $imdbFilename");
        Storage::disk('local')->put($imdbFilename, $titlesContents);
        $this->info("Saved $imdbFilename");

        unset($titlesContents);
    }
}
