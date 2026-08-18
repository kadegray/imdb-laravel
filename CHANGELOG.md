# Release Notes

## 3.0.2 (2026-08-19)

- `imdb:dump` no longer shells out to the `mysql` CLI just to list `imdb_*` tables — it now uses Laravel's own DB connection, which also fixes `port`/`unix_socket` being silently dropped from the connection args.
- `imdb:dump`'s `mysqldump` detection now also checks common Homebrew keg-only install paths and accepts `mariadb-dump` as an alternative, instead of only checking `$PATH`.
- `imdb:dump` no longer hard-fails when no `mysqldump`/`mariadb-dump` binary is found — it automatically falls back to a built-in, dependency-free PHP dump (reading each table via the existing DB connection and writing escaped `INSERT` statements directly).

## 3.0.1 (2026-08-19)

- Removed dangling `ImdbTitle::principals()` relation (referenced the `ImdbPrincipal` model deleted in v3.0.0).
- `imdb:dump` no longer exposes the MySQL password on the process list; the table dump now runs via `Symfony\Component\Process` with credentials passed through the `MYSQL_PWD` env var instead of an interpolated shell string.
- `imdb:import`'s dataset line-counting no longer shells out to `zcat`/`wc`, so it works cross-platform.
- Corrected composer `type` from `project` to `library`.
- Fixed `imdb:import` row parsing: empty TSV fields no longer shift columns (`\t+` regex split replaced with a plain `\t` split), and rows longer than 4096 bytes no longer get truncated/corrupted (removed `gzgets()`'s length cap).
- Fixed `imdb:import` silently appending `"..."` to truncated title/text values.
- Fixed `imdb:import` creating a bogus empty-string genre when a title's `genres` field is null/empty.
- `imdb:import` now upserts titles and ratings in chunks of 1000 (with an in-memory genre cache and bulk pivot-table writes) instead of issuing several queries per row — makes importing the ~11M-row dataset practical. Requires a new migration adding a unique constraint on `imdb_titles.tconst` (dedupes any pre-existing duplicate rows first).
- `imdb:import` downloads now go through `Http` with a timeout/retry and gzip validation before a file is considered downloaded, instead of a raw `file_get_contents()` that could silently save a corrupt/partial file and get treated as "already downloaded" forever after. Downloads also stream straight to disk (instead of buffering the whole file in memory) and show a real byte-progress bar.
- Added `imdb:import --fresh`, `--limit=`, and `--only=titles|ratings` options, plus an import summary table (imported/skipped/matched counts).
- Removed stale `parent_tconst`/`season_number`/`episode_number` entries from `ImdbTitle::$fillable` (columns dropped back in v3.0.0).

## 3.0.0 (2022-06-02)

- Dropped cast/crew/name support entirely: removed the `imdb_names`, `imdb_principals`, `imdb_crew`, `imdb_professions`, `imdb_characters`, `imdb_directors`, and `imdb_writers` tables (plus their pivot tables), deleted the `ImdbName`, `ImdbPrincipal`, `ImdbCrew`, `ImdbCharacter`, `ImdbDirector`, `ImdbWriter`, and `ImdbProfession` models, and removed the corresponding `importNames()`/`importPrincipals()`/`importCrew()` steps from `imdb:import`.
- Dropped TV episode support: removed `parent_tconst`, `season_number`, and `episode_number` from `imdb_titles`, and stopped importing episode data.
- Reworked `imdb:dump` to actually shell out to `mysqldump` and back up the `imdb_*` tables (previously a stub).
- Known issue (fixed above, unreleased): this release left `ImdbTitle::principals()` in place pointing at the now-deleted `ImdbPrincipal` model.

## 2.0.3 (2022-02-13)

Fixed an "undefined array key" error in `imdb:import` by switching row-value lookups from direct array access (`$row[$index]`) to `data_get($row, $index)`, so rows with fewer columns than the header no longer crash the import.

## 2.0.2 (2022-02-13)

Removed the 6,000-row cap on `imdb:import` (`$maxRows`/`$rowCount` early-`break`) — full datasets are now imported instead of a truncated sample.

## 2.0.1 (2022-02-13)

Fixed `getLineCountOfDownload()`: replaced `gzcat file | wc -l` with `cat file | zcat | wc -l`, since `gzcat` isn't available on every system.

## 2.0.0 (2022-02-13)

- Enabled the full `imdb:import` pipeline — file downloads, ratings, episode data, names, principals, and crew imports were previously commented out and are now active.
- Changed `imdb_titles.primary_title` and `original_title` from `text` to `string` (varchar) columns.
- Changed composer `type` from `library` to `project`.

## 1.0.0 (2022-02-13)

Initial release: migrations for the IMDB tables, Eloquent models, and the `imdb:import`/`imdb:dump` artisan commands.
