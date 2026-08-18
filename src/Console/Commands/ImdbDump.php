<?php

namespace KadeGray\ImdbLaravel\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ImdbDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imdb:dump';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump imdb_* mysql tables.';

    public $folderName = 'imdb-data-backups';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function shellCommandExists($command): bool
    {
        return (new ExecutableFinder())->find($command) !== null;
    }

    public function createGitIgnoreFile()
    {
        $databasePath = $this->getDatabasePath();
        $directory = database_path($databasePath);
        $filePath = $directory . '.gitignore';

        if (file_exists($filePath)) {

            return;
        }

        $gitignorefile = fopen($filePath, 'w');
        fwrite($gitignorefile, "*\n");
        fclose($gitignorefile);
    }

    public function getDatabasePath()
    {
        $ds = DIRECTORY_SEPARATOR;

        return $ds . $this->folderName . $ds;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $mysql = config('database.connections.mysql');
        $host = data_get($mysql, 'host');
        $username = data_get($mysql, 'username');
        $password = data_get($mysql, 'password');
        $database = data_get($mysql, 'database');

        if (!$this->shellCommandExists('mysql') || !$this->shellCommandExists('mysqldump')) {
            $this->warn('Please install mysql. (brew install mysql)');

            return 1;
        }

        $databasePath = $this->getDatabasePath();
        $directory = database_path($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = date('Y-m-d-His', time()) . '-dump-' . $database . '.sql';
        $filePath = $directory . $file;

        // Passed via env instead of -p so the password never appears in the process list.
        $env = ['MYSQL_PWD' => $password];

        $listTables = new Process([
            'mysql', '-h', $host, '-u', $username, '-N', '-e', 'SHOW TABLES LIKE "imdb_%"', $database,
        ], null, $env);
        $listTables->run();

        if (!$listTables->isSuccessful()) {
            $this->error('Failed to list imdb_* tables: ' . $listTables->getErrorOutput());

            return 1;
        }

        $tables = array_filter(explode("\n", trim($listTables->getOutput())));

        if (empty($tables)) {
            $this->warn('No imdb_* tables found to dump.');

            return 0;
        }

        $dump = new Process([
            'mysqldump', '--no-create-info', '-h', $host, '-u', $username, $database, ...$tables,
        ], null, $env);
        $dump->run();

        if (!$dump->isSuccessful()) {
            $this->error('mysqldump failed: ' . $dump->getErrorOutput());

            return 1;
        }

        file_put_contents($filePath, $dump->getOutput());

        $this->createGitIgnoreFile();

        $this->info("Dump saved: /database$databasePath");

        return 0;
    }
}
