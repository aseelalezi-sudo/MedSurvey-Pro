#!/usr/bin/env bash
# Exercises the release controller with real Git, symlink switches, archives,
# checksums and flock. External services and package installers are isolated.
set -Eeuo pipefail
script=$(realpath "$(dirname "$0")/../deploy/deploy-medsurvey")
tmp=$(mktemp -d)
trap 'rm -rf -- "$tmp"' EXIT
export PATH="$tmp/bin:$PATH" HEALTH_URL=http://127.0.0.1/api/health MIN_FREE_KIB=1
mkdir -p "$tmp/bin" "$tmp/repo/storage/framework" "$tmp/repo/public"
touch "$tmp/repo/artisan" "$tmp/repo/storage/framework/.gitkeep" "$tmp/repo/public/.gitkeep"
git -C "$tmp/repo" init -q
git -C "$tmp/repo" add .
git -C "$tmp/repo" -c user.email=test@example.test -c user.name=Fixture commit -qm fixture
sha=$(git -C "$tmp/repo" rev-parse HEAD)
printf '#!/bin/bash\nexit 0\n' > "$tmp/bin/composer"
cat > "$tmp/bin/npm" <<'SH'
#!/usr/bin/env bash
set -eu
if [[ $* == 'run build' ]]; then
  [[ $FAIL != build ]]
  mkdir -p public/build/assets
  echo asset > public/build/assets/app.js
  echo sw > public/sw.js
  echo manifest > public/manifest.webmanifest
fi
SH
cat > "$tmp/bin/php" <<'SH'
#!/usr/bin/env bash
set -eu
if [[ $1 == -r ]]; then
  case "$2" in
    *'BACKUP_FILE='*) [[ $FAIL != backup ]]; mkdir -p "$BACKUP_ROOT"; file="$BACKUP_ROOT/medsurvey_backup_20261127_010101.sql.gz"; echo schema | gzip > "$file"; echo "BACKUP_FILE=$file";;
    *json_decode*) read -r value; [[ $value == '{"status":"ok"}' ]];;
  esac
  exit 0
fi
case "$2" in
 down) touch storage/framework/down; [[ $FAIL != down_partial ]];;
 up) rm -f storage/framework/down; [[ $FAIL != up_partial ]];;
 migrate) [[ -f storage/framework/down && $FAIL != migration ]];;
 storage:link) ln -s "$ROOT/shared/storage/app/public" public/storage;;
 view:cache) [[ -f storage/framework/down ]]; if [[ $FAIL == lost_maintenance ]]; then rm storage/framework/down; fi;;
esac
SH
printf '#!/bin/bash\n[[ -e "$ROOT/closed" && ! -e "$ROOT/current/storage/framework/down" && $FAIL != readiness ]] || exit 22\necho probe >> "$ROOT/events"\necho '\''{"status":"ok"}'\''\n' > "$tmp/bin/curl"
chmod +x "$tmp/bin/"*
for FAIL in build backup migration reload readiness lock maintenance lost_maintenance close down_partial up_partial pause resume open unsafe_backup recovery_mode env_mode unsafe_hook public_link success; do
  export FAIL ROOT="$tmp/$FAIL"
  mkdir -p "$ROOT"/{hooks,recovery,releases/old/public/build/assets,shared/storage/framework,shared/storage/app/{public,private}}
  ln -s "$tmp/repo" "$ROOT/repo"
  ln -s "$ROOT/shared/storage" "$ROOT/releases/old/storage"
  touch "$ROOT/shared/.env" "$ROOT/releases/old/artisan"
  chmod 640 "$ROOT/shared/.env"
  ln -s "$ROOT/shared/.env" "$ROOT/releases/old/.env"
  chmod 700 "$ROOT/recovery"
  echo patient-fixture > "$ROOT/shared/storage/app/private/document"
  ln -s "$ROOT/releases/old" "$ROOT/current"
  for hook in check close pause reload resume open; do
    cat > "$ROOT/hooks/$hook" <<'HOOK'
#!/usr/bin/env bash
set -eu
name=$(basename "$0")
echo "$name" >> "$ROOT/events"
case "$name" in
 close) touch "$ROOT/closed";;
 pause) touch "$ROOT/paused";;
 resume) rm -f "$ROOT/paused";;
 open) [[ $(tail -n 3 "$ROOT/events") == *probe* && ! -e "$ROOT/paused" ]]; rm -f "$ROOT/closed";;
esac
[[ $FAIL != "$name" ]]
HOOK
    chmod +x "$ROOT/hooks/$hook"
  done
  if [[ $FAIL == lock ]]; then exec 8>"$ROOT/deploy.lock"; flock 8; fi
  if [[ $FAIL == maintenance ]]; then touch "$ROOT/shared/storage/framework/down"; fi
  if [[ $FAIL == recovery_mode ]]; then chmod 755 "$ROOT/recovery"; fi
  if [[ $FAIL == env_mode ]]; then chmod 644 "$ROOT/shared/.env"; fi
  if [[ $FAIL == unsafe_hook ]]; then chmod 777 "$ROOT/hooks/check"; fi
  if [[ $FAIL == public_link ]]; then ln -s "$ROOT/recovery" "$ROOT/shared/storage/app/public/alias"; fi
  unset BACKUP_ROOT
  if [[ $FAIL == unsafe_backup ]]; then export BACKUP_ROOT="$ROOT/shared/storage/app/public/sql"; fi
  rc=0; bash "$script" "$ROOT" "$sha" > "$ROOT/transcript" 2>&1 || rc=$?
  if [[ $FAIL == lock ]]; then exec 8>&-; fi
  if [[ $FAIL == success ]]; then
    if (( rc )); then cat "$ROOT/transcript"; exit 1; fi
    [[ $(realpath "$ROOT/current") != "$ROOT/releases/old" ]]
    [[ ! -e "$ROOT/shared/storage/framework/down" ]]
    (cd "$ROOT"/recovery/* && sha256sum -c SHA256SUMS && tar -xzOf uploads.tar.gz private/document | grep -qx patient-fixture)
  else
    [[ $rc != 0 ]] && ! grep -q 'Deployment healthy:' "$ROOT/transcript" || exit 1
    case "$FAIL" in
      migration|lost_maintenance|reload|readiness|close|up_partial|resume|open|maintenance) test -f "$ROOT/shared/storage/framework/down";;
      *) test ! -f "$ROOT/shared/storage/framework/down";;
    esac
    case "$FAIL" in build|backup|migration|lock|maintenance) test "$(realpath "$ROOT/current")" = "$ROOT/releases/old";; esac
    case "$FAIL" in migration|lost_maintenance|reload|readiness|resume|open) test -e "$ROOT/closed";; down_partial|pause|backup) test ! -e "$ROOT/closed"; test ! -e "$ROOT/paused";; esac
  fi
  echo "PASS: $FAIL"
done

# Hold a real deployment in its check hook while a second process attempts the same root.
initial=$(realpath "$ROOT/current")
cat > "$ROOT/hooks/check" <<'HOOK'
#!/bin/bash
touch "$ROOT/entered"
for ((i=0;i<200;i++)); do [[ -e $ROOT/continue ]] && exit 0; sleep .05; done
exit 1
HOOK
bash "$script" "$ROOT" "$sha" > "$ROOT/first" 2>&1 &
pid=$!
for ((i=0;i<200;i++)); do [[ -e $ROOT/entered ]] && break; sleep .05; done
test -e "$ROOT/entered"
if bash "$script" "$ROOT" "$sha" > "$ROOT/second" 2>&1; then exit 1; fi
grep -q 'holds the lock' "$ROOT/second"
test "$(realpath "$ROOT/current")" = "$initial"
touch "$ROOT/continue"
wait "$pid"
active=$(realpath "$ROOT/current")
test "$active" != "$initial"
found=0
for record in "$ROOT"/recovery/*; do
  if [[ $(cat "$record/release") == "$active" ]]; then test "$(cat "$record/previous")" = "$initial"; found=1; fi
done
test "$found" = 1
echo 'PASS: overlapping deployments, second activation and rollback pointer'

cat > "$tmp/bin/deploy-medsurvey" <<'SH'
#!/bin/bash
echo do-not-disclose-fixture && php artisan down
SH
chmod 750 "$tmp/bin/deploy-medsurvey"
bash "$(dirname "$script")/ubuntu-inventory.sh" "$ROOT/current" > "$tmp/inventory"
grep -q artisan-down "$tmp/inventory"
! grep -q do-not-disclose-fixture "$tmp/inventory" || exit 1
echo 'PASS: read-only deployment inventory redacts script arguments'
