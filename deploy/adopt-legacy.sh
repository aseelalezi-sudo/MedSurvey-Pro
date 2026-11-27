#!/usr/bin/env bash
# Prepare an unpublished bootstrap release beside a frozen, known legacy checkout.
set -Eeuo pipefail
umask 027
root=$(realpath -e "${1:?ROOT}")
legacy=$(realpath -e "${2:?LEGACY}")
rev=${3:?approved full legacy commit}
[[ $rev =~ ^[a-f0-9]{40}$ && $root != "$legacy" && $root != "$legacy/"* && $legacy != "$root/"* ]]
[[ -d $root/releases && -d $root/shared && ! -L $root/deploy.lock ]]
exec 9>>"$root/deploy.lock"
flock -n 9
for dir in releases shared recovery; do [[ $(realpath -e "$root/$dir") == "$root/$dir" ]]; done
for dir in "$root" "$root/releases" "$root/shared"; do [[ -z $(find "$dir" -maxdepth 0 -perm /022 -print) ]]; done
[[ $(stat -c %a "$root/recovery") == 700 ]]
[[ $(stat -c %u "$root/recovery") == "$(id -u)" ]]
[[ ! -e $root/current && ! -L $root/current && -f $legacy/.env && -f $legacy/storage/framework/down ]]
[[ ! -L $legacy/public && -z $(find -L "$legacy/.env" -maxdepth 0 -perm /027 -print) ]]
envpath=$(realpath -e "$legacy/.env")
[[ $envpath != "$legacy/public/"* && $envpath != "$(realpath -e "$legacy/storage/app/public")/"* ]]
[[ $(git -C "$legacy" rev-parse HEAD) == "$rev" && $(git -C "$root/repo" rev-parse "$rev^{commit}") == "$rev" ]]
git -C "$legacy" diff --quiet "$rev" -- . ':(exclude)public/sw.js' ':(exclude)public/manifest.webmanifest' ':(exclude)public/workbox-*.js'
# Unknown local runtime files need individual review before adoption.
[[ -z $(git -C "$legacy" ls-files --others --exclude-standard -- app bootstrap config database resources routes public ':(exclude)public/storage' ':(exclude)public/workbox-*.js') ]]
for path in .env storage; do
  dest="$root/shared/$path"
  if [[ -L $dest ]]; then [[ $dest -ef "$legacy/$path" ]]; else ln -sT "$(realpath -e "$legacy/$path")" "$dest"; fi
done
bootstrap=$(mktemp -d "$root/releases/legacy-${rev:0:12}.XXXXXXXX")
chmod g+rx "$bootstrap"
git -C "$root/repo" archive "$rev" | tar -xf - -C "$bootstrap"
# Use the verified installed dependencies and generated public assets from the frozen release.
[[ -d $legacy/vendor && -s $legacy/public/build/manifest.json && -s $legacy/public/sw.js ]]
cp -a "$legacy/vendor" "$bootstrap/vendor"
cp -a "$legacy/public/build" "$bootstrap/public/build"
# Copies may retain legacy 0775/0664 modes. Normalize only the staged tree.
find "$bootstrap" -type d -exec chmod u+rwx,g+rx,go-w,o-rx {} +
find "$bootstrap" -type f -exec chmod u+rw,g+r,go-w,o-rx {} +
for f in "$legacy/public/sw.js" "$legacy/public/manifest.webmanifest" "$legacy"/public/workbox-*.js; do
  [[ ! -f $f ]] || cp "$f" "$bootstrap/public/"
done
mv "$bootstrap/storage" "$bootstrap/.storage-skeleton"
ln -s "$root/shared/storage" "$bootstrap/storage"
ln -s "$root/shared/.env" "$bootstrap/.env"
ln -s "$root/shared/storage/app/public" "$bootstrap/public/storage"
# Compare the effective key, database identity, session/cache and maintenance configuration,
# including legacy config-cache overrides; the private fingerprints never leave the process.
identity() { (cd "$1" && php -r '
require "vendor/autoload.php"; $app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=$app->make("db")->connection(); $key=config("app.key");
if (!$key || config("app.maintenance.driver")!=="file") exit(1);
echo hash("sha256",serialize([$key,$db->getConfig(),$db->selectOne("select database() as db")->db,
config("session.driver"),config("session.cookie"),config("cache.default"),config("queue.default")]));
'); }
old_identity=$(identity "$legacy")
new_identity=$(identity "$bootstrap")
[[ $old_identity == "$new_identity" ]]
# This is preparation only: no service changes, database writes, or key generation.
ln -sT "$bootstrap" "$root/current"
printf 'Prepared %s; traffic cutover is a separate operator step.\n' "$bootstrap"
