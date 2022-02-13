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
        $host = config('database.connections.mysql.host');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $database = config('database.connections.mysql.database');

        $ts = time();
        $ds = DIRECTORY_SEPARATOR;

        $directory = database_path() . $ds . 'backups' . $ds;
        $file = date('Y-m-d-His', $ts) . '-dump-' . $database . '.sql';

        $command = sprintf(
            'mysql %s -u %s -p%s -e \'show tables like "imdb_%%"\''
                . ' | grep -v Tables_in'
                . ' | xargs mysqldump -u %s -p\'%s\' %s > %s',
            $database,
            $username,
            $password,
            $username,
            $password,
            $database,
            $directory . $file
        );

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        exec($command);

        $this->info("Dump saved: /backups/$file");

        return 0;
    }
}
