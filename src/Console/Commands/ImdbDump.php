<?php

namespace KadeGray\ImdbLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    protected const NATIVE_DUMP_CHUNK_SIZE = 500;

    // Homebrew's mysql-client/mariadb formulas are keg-only and never get
    // symlinked onto $PATH, so ExecutableFinder alone won't see them.
    protected const HOMEBREW_DUMP_PATHS = [
        '/opt/homebrew/opt/mysql-client/bin/mysqldump',
        '/usr/local/opt/mysql-client/bin/mysqldump',
        '/opt/homebrew/opt/mariadb/bin/mariadb-dump',
        '/usr/local/opt/mariadb/bin/mariadb-dump',
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
        $connection = [
            'host' => data_get($mysql, 'host'),
            'port' => data_get($mysql, 'port'),
            'username' => data_get($mysql, 'username'),
            'password' => data_get($mysql, 'password'),
            'database' => data_get($mysql, 'database'),
            'socket' => data_get($mysql, 'unix_socket'),
        ];

        $tables = $this->getImdbTables();

        if (empty($tables)) {
            $this->warn('No imdb_* tables found to dump.');

            return 0;
        }

        $databasePath = $this->getDatabasePath();
        $directory = database_path($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = date('Y-m-d-His', time()) . '-dump-' . $connection['database'] . '.sql';
        $filePath = $directory . $file;

        $dumpBinary = $this->findDumpBinary();

        if ($dumpBinary) {
            if (!$this->dumpTablesWithBinary($dumpBinary, $tables, $filePath, $connection)) {
                return 1;
            }
        } else {
            $this->info('No mysqldump/mariadb-dump binary found — falling back to a built-in PHP dump.');
            $this->info('For a faster, more complete dump, install a MySQL client (e.g. `brew install mysql-client` on macOS, adding its bin directory to PATH) and re-run this command.');
            $this->dumpTablesNatively($tables, $filePath);
        }

        $this->createGitIgnoreFile();

        $this->info("Dump saved: /database$databasePath");

        return 0;
    }

    /**
     * List imdb_* tables using Laravel's own (already-correctly-configured) connection,
     * instead of shelling out to the mysql CLI just to run SHOW TABLES.
     */
    public function getImdbTables(): array
    {
        $rows = DB::connection('mysql')->select('SHOW TABLES LIKE "imdb_%"');

        return array_map(function ($row) {
            return array_values((array) $row)[0];
        }, $rows);
    }

    /**
     * Resolve a usable mysqldump/mariadb-dump binary: $PATH first, then common
     * Homebrew keg-only install locations that never make it onto $PATH.
     */
    public function findDumpBinary(): ?string
    {
        $finder = new ExecutableFinder();

        if ($path = $finder->find('mysqldump')) {
            return $path;
        }

        if ($path = $finder->find('mariadb-dump')) {
            return $path;
        }

        foreach (self::HOMEBREW_DUMP_PATHS as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function dumpTablesWithBinary(string $binary, array $tables, string $filePath, array $connection): bool
    {
        $env = ['MYSQL_PWD' => $connection['password']];

        $args = [$binary, '--no-create-info', '-h', $connection['host']];

        if (!empty($connection['port'])) {
            $args[] = '-P';
            $args[] = (string) $connection['port'];
        }

        if (!empty($connection['socket'])) {
            $args[] = '--socket=' . $connection['socket'];
        }

        $args[] = '-u';
        $args[] = $connection['username'];
        $args[] = $connection['database'];

        array_push($args, ...$tables);

        $dump = new Process($args, null, $env);
        $dump->run();

        if (!$dump->isSuccessful()) {
            $this->error('mysqldump failed: ' . $dump->getErrorOutput());

            return false;
        }

        file_put_contents($filePath, $dump->getOutput());

        return true;
    }

    /**
     * Zero-external-dependency fallback: reads each table via the DB connection
     * Laravel already has open and writes plain INSERT statements directly.
     */
    public function dumpTablesNatively(array $tables, string $filePath): void
    {
        $connection = DB::connection('mysql');
        $pdo = $connection->getPdo();

        $handle = fopen($filePath, 'w');
        fwrite($handle, "-- Generated by imdb:dump (native PHP fallback)\n\n");

        foreach ($tables as $table) {
            $columns = Schema::connection('mysql')->getColumnListing($table);

            if (empty($columns)) {
                continue;
            }

            $quotedColumns = implode(', ', array_map(fn ($column) => "`$column`", $columns));

            fwrite($handle, "-- Dump of table `$table`\n");

            $connection->table($table)
                ->orderBy('id')
                ->chunk(self::NATIVE_DUMP_CHUNK_SIZE, function ($rows) use ($handle, $table, $columns, $quotedColumns, $pdo) {
                    $valueRows = [];

                    foreach ($rows as $row) {
                        $rowArray = (array) $row;

                        $values = array_map(function ($column) use ($rowArray, $pdo) {
                            $value = $rowArray[$column] ?? null;

                            return $value === null ? 'NULL' : $pdo->quote((string) $value);
                        }, $columns);

                        $valueRows[] = '(' . implode(', ', $values) . ')';
                    }

                    if (empty($valueRows)) {
                        return;
                    }

                    fwrite(
                        $handle,
                        "INSERT INTO `$table` ($quotedColumns) VALUES\n" . implode(",\n", $valueRows) . ";\n"
                    );
                });

            fwrite($handle, "\n");
        }

        fclose($handle);
    }
}
