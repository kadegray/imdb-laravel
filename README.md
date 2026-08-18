## IMDB Laravel

Imports IMDB's public movie title, genre, and rating datasets into your Laravel app's database, with migrations, Eloquent models, and artisan commands.

### Installation

```
composer require kadegray/imdb-laravel
php artisan migrate
```

### Import

Downloads `title.basics.tsv.gz` and `title.ratings.tsv.gz` from `datasets.imdbws.com` and imports movies (titles, genres, and ratings) into the `imdb_titles` and `imdb_genres` tables. Non-movie titles and adult content are skipped.

```
php artisan imdb:import
```

Options:
- `--fresh` — re-download the source files even if already present.
- `--limit=N` — only process the first N data rows of each file (useful for local testing instead of waiting on the full ~11M-row dataset).
- `--only=titles|ratings` — run just the titles or just the ratings step.

### Dump

Backs up the `imdb_*` MySQL tables to `database/imdb-data-backups/`. Requires a MySQL connection and the `mysql`/`mysqldump` CLI tools.

```
php artisan imdb:dump
```
