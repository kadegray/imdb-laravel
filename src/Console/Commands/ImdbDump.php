<?php

namespace KadeGray\ImdbLaravel\Console\Commands;

use Illuminate\Console\Command;

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
        $windows = strpos(PHP_OS, 'WIN') === 0;
        $test = $windows ? 'where' : 'command -v';
        return is_executable(trim(shell_exec("$test $command")));
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

        $ts = time();
        $ds = DIRECTORY_SEPARATOR;

        $databasePath = $this->getDatabasePath();
        $directory = database_path($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = date('Y-m-d-His', $ts) . '-dump-' . $database . '.sql';

        if (!$this->shellCommandExists('mysql')) {
            $this->warn('Please install mysql. (brew install mysql)');

            return;
        }

        $command = sprintf(
            'mysql -h %s -u %s -p\'%s\' -e \'USE %s; SHOW TABLES LIKE "imdb_%%";\''
                . ' | grep -v Tables_in'
                . ' | xargs mysqldump --no-create-info -h %s -u %s -p\'%s\' %s > %s',
            $host,
            $username,
            $password,
            $database,
            $host,
            $username,
            $password,
            $database,
            $directory . $file
        );

        exec($command);

        $this->createGitIgnoreFile();

        $this->info("Dump saved: /database$databasePath");

        return 0;
    }
}
