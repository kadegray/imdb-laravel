<?php

namespace KadeGray\ImdbLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
    protected $signature = 'imdb:import
        {--fresh : Re-download source files even if already present}
        {--limit= : Limit the number of data rows processed per file (for local testing)}
        {--only= : Restrict import to "titles" or "ratings" (default: both)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import IMDB datasets.';

    protected const CHUNK_SIZE = 1000;

    protected array $stats = [
        'titles_imported' => 0,
        'titles_skipped_non_movie' => 0,
        'titles_skipped_adult' => 0,
        'ratings_matched' => 0,
        'ratings_unmatched' => 0,
    ];

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
        $only = $this->option('only');

        if ($only !== null && !in_array($only, ['titles', 'ratings'])) {
            $this->error('--only must be "titles" or "ratings"');

            return 1;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $fresh = (bool) $this->option('fresh');

        $files = match ($only) {
            'titles' => ['title.basics.tsv.gz'],
            'ratings' => ['title.ratings.tsv.gz'],
            default => ['title.basics.tsv.gz', 'title.ratings.tsv.gz'],
        };

        if (!$this->downloadFiles($files, $fresh)) {
            $this->error('Aborting import due to download failure.');

            return 1;
        }

        if ($only === null || $only === 'titles') {
            $this->importTitles($limit);
        }

        if ($only === null || $only === 'ratings') {
            $this->importTitleRatings($limit);
        }

        $this->printSummary();

        return 0;
    }

    public function downloadFiles(array $files, bool $fresh = false)
    {
        foreach ($files as $imdbFilename) {

            if (!$fresh && $this->hasDownloadFile($imdbFilename)) {
                $this->info("$imdbFilename already downloaded");
                continue;
            }

            if (!$this->downloadFile($imdbFilename)) {
                return false;
            }
        }

        return true;
    }

    public function importTitles($limit = null)
    {
        $imdbFileName = 'title.basics.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Titles ($imdbFileName)");

        $genreCache = ImdbGenre::pluck('id', 'name')->all();
        $buffer = [];

        $this->iterateOverFile($imdbFileName, $limit, function ($headings, $row) use (&$buffer, &$genreCache) {

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
                    $this->stats['titles_skipped_non_movie']++;

                    return;
                }

                // no adult
                if (
                    $heading == 'is_adult'
                    && $value == '1'
                ) {
                    $this->stats['titles_skipped_adult']++;

                    return;
                }

                if (
                    $heading == 'genres'
                    && Str::of($value)->contains('Adult')
                ) {
                    $this->stats['titles_skipped_adult']++;

                    return;
                }

                data_set($imdbTitleData, $heading, $value);
            }

            $genresValue = $imdbTitleData['genres'] ?? null;
            $genreNames = empty($genresValue)
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $genresValue))));

            // explicitly whitelisted to the imdb_titles columns — upsert() writes
            // straight to the table and bypasses Eloquent's $fillable protection.
            $buffer[] = [
                'tconst' => $tconst,
                'title_type' => $imdbTitleData['title_type'] ?? null,
                'primary_title' => $imdbTitleData['primary_title'] ?? null,
                'original_title' => $imdbTitleData['original_title'] ?? null,
                'start_year' => $imdbTitleData['start_year'] ?? null,
                'end_year' => $imdbTitleData['end_year'] ?? null,
                'runtime_minutes' => $imdbTitleData['runtime_minutes'] ?? null,
                'genres' => $genresValue,
                'genre_names' => $genreNames,
            ];

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->flushTitleBuffer($buffer, $genreCache);
                $buffer = [];
            }
        });

        $this->flushTitleBuffer($buffer, $genreCache);
    }

    protected function flushTitleBuffer(array $buffer, array &$genreCache): void
    {
        if (empty($buffer)) {
            return;
        }

        $allGenreNames = collect($buffer)->pluck('genre_names')->flatten()->unique()->values()->all();
        $newGenreNames = array_diff($allGenreNames, array_keys($genreCache));

        if (!empty($newGenreNames)) {
            ImdbGenre::insertOrIgnore(array_map(fn ($name) => ['name' => $name], $newGenreNames));

            $genreCache = $genreCache + ImdbGenre::whereIn('name', $newGenreNames)->pluck('id', 'name')->all();
        }

        $titleRows = array_map(function ($row) {
            unset($row['genre_names']);

            return $row;
        }, $buffer);

        // average_rating/num_votes are deliberately excluded from the update columns:
        // title.basics never carries ratings, so including them would null out
        // ratings written by importTitleRatings() on every re-run.
        ImdbTitle::upsert(
            $titleRows,
            ['tconst'],
            ['title_type', 'primary_title', 'original_title', 'start_year', 'end_year', 'runtime_minutes', 'genres']
        );

        $titleIds = ImdbTitle::whereIn('tconst', array_column($buffer, 'tconst'))->pluck('id', 'tconst');

        DB::table('imdb_genre_imdb_title')->whereIn('imdb_title_id', $titleIds->values()->all())->delete();

        $pivotRows = [];
        foreach ($buffer as $row) {
            $titleId = $titleIds[$row['tconst']] ?? null;
            if (!$titleId) {
                continue;
            }

            foreach ($row['genre_names'] as $genreName) {
                $genreId = $genreCache[$genreName] ?? null;
                if (!$genreId) {
                    continue;
                }

                $pivotRows["$titleId-$genreId"] = [
                    'imdb_title_id' => $titleId,
                    'imdb_genre_id' => $genreId,
                ];
            }
        }

        if (!empty($pivotRows)) {
            DB::table('imdb_genre_imdb_title')->insert(array_values($pivotRows));
        }

        $this->stats['titles_imported'] += count($titleRows);
    }

    public function importTitleRatings($limit = null)
    {
        $imdbFileName = 'title.ratings.tsv.gz';

        $this->newLine(2);
        $this->line("Importing Title Ratings ($imdbFileName)");

        $buffer = [];

        $this->iterateOverFile($imdbFileName, $limit, function ($headings, $row) use (&$buffer) {

            $tconst = $row[0];
            $ratingData = ['tconst' => $tconst];

            foreach ($headings as $index => $heading) {

                $heading = Str::snake($heading);
                $value = $this->handleValue(data_get($row, $index));

                if (in_array($heading, [
                    'average_rating',
                    'num_votes',
                ])) {
                    $ratingData[$heading] = $value;
                }
            }

            $buffer[] = $ratingData;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->flushRatingsBuffer($buffer);
                $buffer = [];
            }
        });

        $this->flushRatingsBuffer($buffer);
    }

    protected function flushRatingsBuffer(array $buffer): void
    {
        if (empty($buffer)) {
            return;
        }

        // title.ratings covers every title type, not just the movies importTitles()
        // keeps — this guard stops non-movie tconsts from being re-inserted as bare rows.
        $existing = ImdbTitle::whereIn('tconst', array_column($buffer, 'tconst'))->pluck('tconst')->flip();

        $matched = array_values(array_filter($buffer, fn ($row) => isset($existing[$row['tconst']])));

        $this->stats['ratings_unmatched'] += count($buffer) - count($matched);

        if (empty($matched)) {
            return;
        }

        ImdbTitle::upsert($matched, ['tconst'], ['average_rating', 'num_votes']);

        $this->stats['ratings_matched'] += count($matched);
    }

    public function downloadFile($imdbFilename)
    {
        $this->line("Downloading $imdbFilename");

        $tmpFilename = "$imdbFilename.tmp";
        $tmpPath = Storage::disk('local')->path($tmpFilename);

        if (!is_dir(dirname($tmpPath))) {
            mkdir(dirname($tmpPath), 0755, true);
        }

        $bar = null;

        // Streams straight to disk instead of buffering the whole (multi-hundred-MB)
        // file in memory, and reports real byte progress via Guzzle's "progress" option.
        $response = Http::timeout(180)->retry(3, 5000)->withOptions([
            'sink' => $tmpPath,
            'progress' => function ($downloadTotal, $downloadedBytes) use (&$bar) {
                if ($downloadedBytes <= 0) {
                    return;
                }

                if (!$bar) {
                    $bar = $this->output->createProgressBar($downloadTotal > 0 ? $downloadTotal : 0);
                    $bar->setFormat($downloadTotal > 0 ? 'very_verbose' : 'debug');
                }

                $bar->setProgress((int) $downloadedBytes);
            },
        ])->get("https://datasets.imdbws.com/$imdbFilename");

        if ($bar) {
            $bar->finish();
            $this->newLine();
        }

        if (!$response->successful()) {
            $this->error("Failed to download $imdbFilename: HTTP {$response->status()}");
            Storage::disk('local')->delete($tmpFilename);

            return false;
        }

        if (!$this->isValidGzipFile($tmpFilename)) {
            $this->error("Downloaded $imdbFilename failed validation");
            Storage::disk('local')->delete($tmpFilename);

            return false;
        }

        Storage::disk('local')->move($tmpFilename, $imdbFilename);
        $this->info("Saved $imdbFilename");

        return true;
    }

    public function isValidGzipFile($imdbFilename)
    {
        $path = Storage::disk('local')->path($imdbFilename);
        $handle = @gzopen($path, 'r');

        if (!$handle) {
            return false;
        }

        $line = gzgets($handle);
        gzclose($handle);

        return $line !== false;
    }

    public function iterateOverFile($imdbFilename, $limit, $insertRow)
    {
        $filename = Storage::disk('local')->path($imdbFilename);
        $handle = gzopen($filename, 'r');

        $headings = null;
        $rowCount = 0;

        $totalRows = $limit !== null
            ? $limit + 1
            : $this->getLineCountOfDownload($imdbFilename);

        $bar = $this->output->createProgressBar($totalRows);
        $bar->setFormat('very_verbose');

        while (($line = gzgets($handle)) !== false) {
            $bar->advance();

            $row = explode("\t", rtrim($line, "\r\n"));

            if (!$headings) {
                $headings = $row;
                continue;
            }

            $insertRow($headings, $row);

            $rowCount++;
            if ($limit !== null && $rowCount >= $limit) {
                break;
            }
        }

        gzclose($handle);
        $bar->finish();
    }

    public function getLineCountOfDownload($filename)
    {
        $filePath = Storage::disk('local')->path($filename);
        $handle = gzopen($filePath, 'r');

        $lineCount = 0;
        while (!gzeof($handle)) {
            gzgets($handle, 4096);
            $lineCount++;
        }

        gzclose($handle);

        return $lineCount;
    }

    public function handleValue($value)
    {
        if (Str::of($value)->startsWith('\N')) {
            return null;
        }

        $value = Str::of($value)->trim();
        $value = Str::limit($value, 252, '');

        return $value;
    }

    public function hasDownloadFile($imdbFilename)
    {
        return Storage::disk('local')->exists($imdbFilename);
    }

    protected function printSummary()
    {
        $this->newLine(2);
        $this->table(
            ['Metric', 'Count'],
            collect($this->stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all()
        );
    }
}
