#!/usr/bin/env bash
# Synthetic-only. Create a new fixture schema on the CI MySQL service.
set -Eeuo pipefail
trap 'echo "Synthetic field audit failed at line $LINENO" >&2' ERR
[[ ${CI:-} == true && ${DB_HOST:-} == 127.0.0.1 && ${DB_DATABASE:-} == medsurvey_testing ]]
repo=$(pwd -P)
ops=$repo/deploy/ubuntu
tmp=$(mktemp -d)
trap 'rm -rf -- "$tmp"' EXIT
umask 077
bundle=$tmp/bundle
mkdir -p "$bundle" "$tmp"/{public,private}
sql=(mysql --protocol=tcp -h 127.0.0.1 -u root -N -B)
"${sql[@]}" -e 'CREATE DATABASE medsurvey_field_test'
fixture=("${sql[@]}" medsurvey_field_test)
cat > "$tmp/schema.sql" <<'SQL'
CREATE TABLE tenants (id INT PRIMARY KEY);
CREATE TABLE users (id INT PRIMARY KEY, tenantId INT REFERENCES tenants(id));
CREATE TABLE surveys (id INT PRIMARY KEY, tenantId INT, FOREIGN KEY (tenantId) REFERENCES tenants(id));
CREATE TABLE survey_responses (id INT PRIMARY KEY, surveyId INT, tenantId INT, FOREIGN KEY(surveyId) REFERENCES surveys(id));
CREATE TABLE jobs (queue VARCHAR(80), attempts INT, available_at INT, reserved_at INT, created_at INT, payload LONGTEXT);
CREATE TABLE failed_jobs (id INT);
INSERT INTO tenants VALUES (1),(2);
INSERT INTO surveys VALUES (1,1),(2,2);
INSERT INTO survey_responses VALUES (1,1,1),(2,2,2);
SET @wrapped=JSON_OBJECT('displayName',CONCAT_WS(CHAR(92 USING utf8mb4),'App','Events','SurveySubmitted'),
 'data',JSON_OBJECT('commandName',CONCAT_WS(CHAR(92 USING utf8mb4),'Illuminate','Broadcasting','BroadcastEvent'),'command','SECRET_TEST_MARKER'));
INSERT INTO jobs VALUES
('default',0,0,NULL,UNIX_TIMESTAMP()-100,@wrapped),
('default',2,0,UNIX_TIMESTAMP()-300,UNIX_TIMESTAMP()-400,@wrapped),
('default',1,UNIX_TIMESTAMP()+200,NULL,UNIX_TIMESTAMP(),'{invalid SECRET_TEST_MARKER'),
('default',1,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),JSON_OBJECT('displayName','SECRET_TEST_MARKER','data',JSON_OBJECT('commandName','SECRET_TEST_MARKER'))),
('medsurvey-live',0,0,NULL,UNIX_TIMESTAMP(),@wrapped);
SQL
"${fixture[@]}" < "$tmp/schema.sql"
dump="$bundle/medsurvey_backup_20000101_030000.sql.gz"
gzip -c "$tmp/schema.sql" > "$dump"
printf '[client]\nhost=127.0.0.1\nuser=root\ndatabase=medsurvey_field_test\n' > "$tmp/readonly.cnf"
# Enforce SELECT-only rights during the audit itself.
"${sql[@]}" -e "CREATE USER 'field_reader'@'%'; GRANT SELECT ON medsurvey_field_test.* TO 'field_reader'@'%'"
sed -i s/user=root/user=field_reader/ "$tmp/readonly.cnf"
bash "$ops/field-audit.sh" "$repo" "$tmp/readonly.cnf" "$bundle" > "$tmp/report"
grep -Fq $'broadcast:SurveySubmitted\t0\tunreserved\t1\t1' "$tmp/report"
grep -Fq $'broadcast:SurveySubmitted\t2\texpired\t1\t1' "$tmp/report"
grep -Fq $'unclassified-envelope\t1\tunreserved\t0\t1' "$tmp/report"
grep -Eq 'unclassified-job:[a-f0-9]{16}[[:space:]]+1[[:space:]]+reserved[[:space:]]+0' "$tmp/report"
if grep -q SECRET_TEST_MARKER "$tmp/report"; then exit 1; fi
grep -Fq "backup_path $dump" "$tmp/report"
[[ $("${fixture[@]}" -e 'SELECT COUNT(*) FROM jobs') == 5 ]]
echo 'PASS: read-only classification, availability, reservation expiry and output redaction'
cp "$dump" "$tmp/bad.gz"
truncate -s -4 "$dump"
if python3 "$ops/latest-backup.py" "$bundle"; then exit 1; fi
mv "$tmp/bad.gz" "$dump"
echo 'PASS: incomplete gzip rejected'
printf 'test file' > "$tmp/private/fixture"
tar -czf "$bundle/uploads.tar.gz" -C "$tmp" public private
(cd "$bundle" && sha256sum "$(basename "$dump")" uploads.tar.gz > SHA256SUMS)
mysql --defaults-file="$tmp/readonly.cnf" -NB < "$ops/source-counts.sql" > "$tmp/counts.json"
python3 "$ops/restore-drill.py" "$bundle" --counts "$tmp/counts.json"
echo 'PASS: offline MySQL restore, exact source counts, foreign keys, tenant consistency and uploads'
printf '\nUPDATE survey_responses SET tenantId=2 WHERE id=1;\n' | gzip >> "$dump"
(cd "$bundle" && sha256sum "$(basename "$dump")" uploads.tar.gz > SHA256SUMS)
if python3 "$ops/restore-drill.py" "$bundle" --counts "$tmp/counts.json" > "$tmp/negative" 2>&1; then exit 1; fi
grep -q 'checking tenant relationships' "$tmp/negative"
echo 'PASS: cross-facility restore inconsistency rejected'
