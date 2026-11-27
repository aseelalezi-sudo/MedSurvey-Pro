-- Run with the read-only application connection at the SAME frozen recovery point.
-- Redirect stdout privately using mysql --batch --skip-column-names.
START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;
SET SESSION group_concat_max_len=1048576;
SELECT CONCAT('SELECT JSON_OBJECT(', GROUP_CONCAT(
    CONCAT(QUOTE(table_name), ',(SELECT COUNT(*) FROM `', REPLACE(table_name,'`','``'), '`)')
    ORDER BY table_name SEPARATOR ','), ')')
INTO @counts_query FROM information_schema.tables
WHERE table_schema=DATABASE() AND table_type='BASE TABLE';
PREPARE counts FROM @counts_query;
EXECUTE counts;
DEALLOCATE PREPARE counts;
COMMIT;
