<?php

namespace KadeGray\ImdbLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KadeGray\ImdbLaravel\Models\ImdbGenre;
use KadeGray\ImdbLaravel\Models\ImdbTitle;

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

        return 0;
    }


    public function downloadFiles()
    {
        foreach ([
            'title.basics.tsv.gz',
            'title.ratings.tsv.gz',
        ] as $imdbFilename) {

            if ($this->hasDownloadFile($imdbFilename)) {
                $this->info("$imdbFilename already downloaded");
                continue;
            }

            $this->downloadFile($imdbFilename);
        }
    }


    public function importTitles()
    {
        $imdbFileName = 'title.basics.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Titles ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $tconst = $row[0];
            $imdbTitleData = [];

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue(data_get($row, $index));

                // only movies
                if (
                    $heading == 'title_type'
                    && !in_array($value, ['movie', 'tvMovie'])
                ) {
                    return;
                }

                // no adult
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

                data_set($imdbTitleData, $heading, $value);
            }

            $imdbTitle = ImdbTitle::updateOrCreate([
                'tconst' => $tconst,
            ], $imdbTitleData);

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

    public function importTitleRatings()
    {
        $imdbFileName = 'title.ratings.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Title Ratings ($imdbFileName)");

        $this->iterateOverFile($imdbFileName, function ($headings, $row) {

            $tconst = $row[0];
            $imdbTitle = ImdbTitle::where('tconst', $tconst)->first();
            if (!$imdbTitle) {

                return;
            }

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue(data_get($row, $index));

                if (in_array($heading, [
                    'average_rating',
                    'num_votes',
                ])) {
                    data_set($imdbTitle, $heading, $value);
                }
            }

            $imdbTitle->save();
        });
    }


    public function downloadFile($imdbFilename)
    {
        $this->line("Downloading $imdbFilename");
        $titlesContents = file_get_contents("https://datasets.imdbws.com/$imdbFilename");
        $this->info("Downloaded $imdbFilename");

        $this->line("Saving $imdbFilename");
        Storage::disk('local')->put($imdbFilename, $titlesContents);
        $this->info("Saved $imdbFilename");

        unset($titlesContents);
    }

    public function iterateOverFile($imdbFilename, $insertRow)
    {
        $filename = storage_path("app/$imdbFilename");
        $handle = gzopen($filename, "r");

        $headings = null;

        $totalRows = $this->getLineCountOfDownload($imdbFilename);

        // $rowCount = 0;
        // $maxRows = 15000;
        // $totalRows = $maxRows;

        $bar = $this->output->createProgressBar($totalRows);
        $bar->setFormat('very_verbose');
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

    public function getLineCountOfDownload($filename)
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

    public function hasDownloadFile($imdbFilename)
    {
        return Storage::disk('local')->exists($imdbFilename);
    }
}
