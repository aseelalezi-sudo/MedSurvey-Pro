# MySQL development and server compatibility

MySQL 8.0.46 is the server reference. CI tests 8.0.46 and the future 8.4.13 target by running PHP against real MySQL. Flutter still uses its device-local SQLite store.

## Optional Docker database

Use a separate clone or worktree. Set MYSQL_DEV_ROOT_PASSWORD and MYSQL_DEV_PASSWORD via a private Compose environment file. The optional development stack publishes port 13306 on loopback. Its container is medsurvey-mysql80-dev and it has a fresh named volume.

~~~sh
docker compose --env-file .env.mysql-dev -p medsurvey-dev-mysql80 -f docker-compose.dev.yml config --quiet
docker compose --env-file .env.mysql-dev -p medsurvey-dev-mysql80 -f docker-compose.dev.yml up -d --wait
~~~

Keep that file private. Set the separate checkout's Laravel connection to mysql, 127.0.0.1, port 13306 and medsurvey_mysql_dev with user medsurvey_dev. Clear any copied DB_URL, DB_SOCKET or config cache. Query SELECT VERSION(), DATABASE() through Laravel and confirm the exact target before migrating. Use a separate *_test schema and restricted test account with the test suite. Set DB_EXPECTED_MYSQL_VERSION to the exact fixture release.

Use Oracle mysql and mysqldump from the server's release series. On Windows set MYSQL_PATH and MYSQLDUMP_PATH to their full paths. On Ubuntu install the matched client packages or use the paired tools in the reviewed Docker image. The server credentials come from the effective Laravel connection. The tool will reject incompatible clients it finds. If no dump program is available, the PHP fallback still writes an application-table backup. It omits views, triggers, routines and events. Site recovery uses its native export. For the application restore path, new backups carry a SHA-256 sidecar and the verifier exposes whether it matched. Older backups without sidecars require an independently recorded SHA-256 to establish integrity. Keep the sidecar with any backup you move.

## Rehearse a MariaDB data transfer

Use a fresh source copy or test fixture and a separate MySQL database. Record source engine, table engines, column definitions, collations, row counts and foreign keys. Check primary and unique keys under the target collation, zero dates, JSON validity, leading and trailing spaces, generated columns and case-sensitive identifiers. Freeze source writes for the final copy. Preserve the MariaDB backup and endpoint for rollback.

1. Build the target tables using the reviewed Laravel migrations. Keep the application and workers disconnected. Make sure the target is empty except for its own migration history.
2. Export application tables only through the source MariaDB client. Use an ordered, explicit table list derived from the foreign-key graph. For tables with dependencies, load parents first. A table with cycles needs a separately validated transformation.
3. Use INSERT statements with complete column names. Omit the source migration history and server schemas. Do a separate review of views, routines, events, triggers, collations or column names that differ. Keep triggers and schedulers paused. Validate each field mapped from a MariaDB JSON alias against the MySQL target type. For licensing, reconcile the legacy payload and preserve the original application key as described below.
4. Import into the new database with foreign keys enforced, compare per-table counts and deterministic row hashes and check tenant relationships. Compare Arabic strings, dates, nulls and logical JSON values, then run the application's entry points against the isolated target. Treat any partial import as a disposable target to recreate after diagnosing the error.
5. Point the development application to the validated instance only after explicit approval. Keep the MariaDB configuration and data available and decide how to carry across any writes admitted after switching.

Example of exporting a set of compatible tables in dependency order while leaving foreign keys enforced at import. Set the schema and list privately, and use protected client option files. Check SELECT VERSION() and DATABASE() on both ends first.

~~~sh
umask 077
mysqldump --defaults-file="$SOURCE_CNF" --default-character-set=utf8mb4 --single-transaction --no-create-info --complete-insert \
  --skip-triggers --hex-blob --compact "$SOURCE_SCHEMA" tenants surveys survey_responses > "$PRIVATE/fixture.sql"
mysql --defaults-file="$TARGET_CNF" --default-character-set=utf8mb4 --binary-mode --local-infile=0 "$EMPTY_TARGET_SCHEMA" < "$PRIVATE/fixture.sql"
~~~

The license migration preserves authenticated ciphertext byte for byte, unwraps authenticated ciphertext quoted as a JSON string and encrypts old structured JSON values. SQL NULL and JSON null stay absent. An undecodable legacy value stops the migration before the column changes. Keep the application writers drained across the migration. The schema remains LONGTEXT if the application is rolled back. To restore the pre-migration JSON schema, restore a verified pre-migration backup in isolation. For a legacy Windows installation, verify app key continuity before moving the license row.

For a native backup, legacy-schema and rollback regression rehearsal, stage the approved image and run tests/mysql-native-roundtrip.py with the exact pinned image from the CI matrix. The script creates and cleans up its own MySQL fixture.
