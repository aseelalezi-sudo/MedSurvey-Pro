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
    *'BACKUP_FILE='*) [[ $FAIL != backup ]]; echo schema | gzip > "$ROOT/shared/backup.sql.gz"; echo "BACKUP_FILE=$ROOT/shared/backup.sql.gz";;
    *json_decode*) read -r value; [[ $value == '{"status":"ok"}' ]];;
  esac
  exit 0
fi
case "$2" in
 down) touch storage/framework/down;;
 up) rm -f storage/framework/down;;
 migrate) [[ -f storage/framework/down && $FAIL != migration ]];;
 view:cache) [[ -f storage/framework/down ]];;
esac
SH
printf '#!/bin/bash\n[[ $FAIL != readiness ]] || exit 22\necho '\''{"status":"ok"}'\''\n' > "$tmp/bin/curl"
chmod +x "$tmp/bin/"*
for FAIL in build backup migration reload readiness lock maintenance success; do
  export FAIL ROOT="$tmp/$FAIL"
  mkdir -p "$ROOT"/{hooks,recovery,releases/old/public/build/assets,shared/storage/framework,shared/storage/app/{public,private}}
  ln -s "$tmp/repo" "$ROOT/repo"
  ln -s "$ROOT/shared/storage" "$ROOT/releases/old/storage"
  touch "$ROOT/shared/.env" "$ROOT/releases/old/artisan"
  echo patient-fixture > "$ROOT/shared/storage/app/private/document"
  ln -s "$ROOT/releases/old" "$ROOT/current"
  for hook in check pause reload resume; do
    printf '#!/bin/bash\necho "%s" >> "$ROOT/events"\n[[ $FAIL != "%s" ]]\n' "$hook" "$hook" > "$ROOT/hooks/$hook"
    chmod +x "$ROOT/hooks/$hook"
  done
  if [[ $FAIL == lock ]]; then exec 8>"$ROOT/deploy.lock"; flock 8; fi
  if [[ $FAIL == maintenance ]]; then touch "$ROOT/shared/storage/framework/down"; fi
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
      migration|reload|readiness|maintenance) test -f "$ROOT/shared/storage/framework/down";;
      *) test ! -f "$ROOT/shared/storage/framework/down";;
    esac
    case "$FAIL" in build|backup|migration|lock|maintenance) test "$(realpath "$ROOT/current")" = "$ROOT/releases/old";; esac
  fi
  echo "PASS: $FAIL"
done
