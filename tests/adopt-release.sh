#!/usr/bin/env bash
# Run with a disposable MySQL database on an isolated test host.
set -Eeuo pipefail
repo=$(realpath -e "${1:?Git repo}")
base=${2:?legacy full commit}
target=${3:?candidate full commit}
: "${DB_HOST:?}" "${DB_USERNAME:?}" "${DB_DATABASE:?}" "${DB_PASSWORD?}"
unset APP_KEY
[[ $DB_DATABASE == pr23_transition_test && $base =~ ^[a-f0-9]{40}$ && $target =~ ^[a-f0-9]{40}$ ]]
[[ $(MYSQL_PWD=$DB_PASSWORD mysql -h "$DB_HOST" -u "$DB_USERNAME" "$DB_DATABASE" -NBe 'SHOW TABLES') == "" ]]
scripts=$(realpath "$(dirname "$0")/../deploy")
tmp=$(mktemp -d)
server=
trap '[[ -z $server ]] || kill "$server" 2>/dev/null || :; rm -rf -- "$tmp"' EXIT
root=$tmp/site
legacy=$tmp/legacy
mkdir -p "$root"/{releases,shared,recovery,hooks}
chmod 700 "$root/recovery"
ln -s "$repo" "$root/repo"
git clone -q --no-local "$repo" "$legacy"
git -C "$legacy" checkout -q "$base"
(
 cd "$legacy"
 if [[ -n ${LEGACY_IMAGE_FILES:-} ]]; then
   cp -a "$LEGACY_IMAGE_FILES/vendor" .
   cp -a "$LEGACY_IMAGE_FILES/public/build" public/
   cp "$LEGACY_IMAGE_FILES/public/sw.js" public/
 else
   composer install --no-dev --no-interaction --prefer-dist --no-scripts
   npm ci --include=dev --no-fund
   npm run build
 fi
 php -r 'file_put_contents(".env", "APP_KEY=base64:".base64_encode(random_bytes(32)).PHP_EOL."APP_ENV=testing".PHP_EOL);'
 chmod 640 .env
 php artisan migrate --force
 MYSQL_PWD=$DB_PASSWORD mysql -h "$DB_HOST" -u "$DB_USERNAME" "$DB_DATABASE" -e 'CREATE TABLE restore_proof (id INT PRIMARY KEY); INSERT INTO restore_proof VALUES (23)'
 echo synthetic-upload > storage/app/public/proof.txt
 echo private-fixture > storage/app/private/proof.txt
 php artisan down
)
export ROOT="$root" MIN_FREE_KIB=1
if [[ ${CHECK_WEB_PERMISSIONS:-0} == 1 ]]; then
  sudo chgrp www-data "$tmp" "$root" "$root"/{releases,shared,hooks} "$legacy/.env" "$legacy/storage/app/public"
  chmod 710 "$tmp"
  chmod 2750 "$root" "$root"/{releases,shared,hooks}
  chmod 2770 "$legacy/storage/app/public"
fi
bash "$scripts/adopt-legacy.sh" "$root" "$legacy" "$base"
[[ $root/current/storage -ef $legacy/storage && $root/current/.env -ef $legacy/.env ]]
if bash "$scripts/adopt-legacy.sh" "$root" "$legacy" "$base"; then exit 1; fi
previous=$(realpath "$root/current")
test -z "$(find "$previous/public" "$previous/vendor" -type f -perm /022 -print -quit)"
test -z "$(find "$previous/public" "$previous/vendor" -type d -perm /022 -print -quit)"
(cd "$previous" && php artisan up)
# Two request paths share one local origin. The fixture public gate survives artisan up.
cat > "$tmp/router.php" <<'PHP'
<?php
$root = getenv('ROOT');
if (isset($_SERVER['HTTP_X_QA_PUBLIC']) && file_exists($root.'/gate')) {
    http_response_code(503); echo 'medsurvey-maintenance'; return;
}
chdir($root.'/current/public');
require getcwd().'/index.php';
PHP
php -S 127.0.0.1:18764 "$tmp/router.php" > "$tmp/http.log" 2>&1 &
server=$!
export HEALTH_URL=http://127.0.0.1:18764/api/health
for ((i=0;i<50;i++)); do if curl -fsS "$HEALTH_URL" >/dev/null 2>&1; then break; fi; sleep .1; done
for name in check close pause reload resume open; do
 cat > "$root/hooks/$name" <<'HOOK'
#!/bin/bash
set -eu
case $(basename "$0") in
 check) curl -fsS "$HEALTH_URL" >/dev/null;;
 close) touch "$ROOT/gate"; test "$(curl -sS -H 'X-QA-Public: 1' -w ' %{http_code}' "$HEALTH_URL")" = 'medsurvey-maintenance 503';;
 reload|resume|pause) test -e "$ROOT/gate";;
 open) test -e "$ROOT/gate"; rm "$ROOT/gate"; curl -fsS -H 'X-QA-Public: 1' "$HEALTH_URL" >/dev/null;;
esac
HOOK
 chmod 750 "$root/hooks/$name"
done
bash "$scripts/deploy-medsurvey" "$root" "$target"
if [[ ${CHECK_WEB_PERMISSIONS:-0} == 1 ]]; then
  web() { sudo -n -u www-data -- "$@"; }
  for p in .env public/index.php bootstrap/cache/config.php; do web test -r "$root/current/$p"; done
  for p in "$root" "$root/hooks" "$root/current/public" "$root/current/.env" "$root/current/bootstrap/cache/config.php"; do
    if web test -w "$p"; then exit 1; fi
  done
  if web test -x "$root/recovery"; then exit 1; fi
  web touch "$root/current/public/storage/web-write-fixture"
  test -f "$legacy/storage/app/public/web-write-fixture"
  echo 'PASS: www-data reads release and environment, writes uploads, cannot modify code/config or traverse recovery'
fi
record=$(dirname "$(find "$root/recovery" -name healthy -print -quit)")
[[ $(cat "$record/previous") == "$previous" ]]
(cd "$record" && sha256sum -c SHA256SUMS && [[ $(tar -xzOf uploads.tar.gz private/proof.txt) == private-fixture ]])
# Restore to a new-only disposable schema and upload directory. Never reuse an existing schema.
sql=(mysql -h "$DB_HOST" -u "$DB_USERNAME")
export MYSQL_PWD=$DB_PASSWORD
"${sql[@]}" -e 'CREATE DATABASE pr23_restore_test'
dump=$(find "$record" -maxdepth 1 -type f -name '*.sql*')
case "$dump" in *.sql.gz) gzip -dc "$dump";; *.sql) cat "$dump";; *) exit 1;; esac | "${sql[@]}" pr23_restore_test
[[ $("${sql[@]}" -NBe 'SELECT id FROM pr23_restore_test.restore_proof') == 23 ]]
mkdir "$tmp/restored"
tar -xzf "$record/uploads.tar.gz" -C "$tmp/restored"
cmp "$tmp/restored/public/proof.txt" "$legacy/storage/app/public/proof.txt"
cmp "$tmp/restored/private/proof.txt" "$legacy/storage/app/private/proof.txt"
[[ $root/current/.env -ef $legacy/.env && $root/current/storage -ef $legacy/storage ]]
# This fixture has a verified compatible schema. Rehearse a gated rollback to the bootstrap.
"$root/hooks/close" "$root/current"
(cd "$root/current" && php artisan down)
"$root/hooks/pause" "$root/current"
ln -s "$previous" "$root/rollback"
mv -Tf "$root/rollback" "$root/current"
"$root/hooks/reload" "$previous"
(cd "$previous" && php artisan up)
curl -fsS "$HEALTH_URL" >/dev/null
"$root/hooks/resume" "$previous"
"$root/hooks/open" "$previous"
[[ $(cat "$root/current/storage/app/public/proof.txt") == synthetic-upload ]]
echo 'PASS: legacy adoption, real legacy BackupService, SQL and upload checksums, HTTP gate, activation and compatible rollback'
