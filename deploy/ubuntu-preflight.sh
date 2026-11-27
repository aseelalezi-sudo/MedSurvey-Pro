#!/usr/bin/env bash
# Read-only legacy inspection. Never bootstrap Laravel or execute the installed command.
set -Eeuo pipefail
legacy=$(realpath -e "${1:?legacy checkout}")
root=$(realpath -m "${2:?proposed release root}")
rev=${3:?full legacy commit}
client=${4:?private MySQL client option file with read-only database and account}
[[ $rev =~ ^[a-f0-9]{40}$ && $root != "$legacy" && $root != "$legacy/"* && $legacy != "$root/"* ]]
[[ -f $client && ! -L $client && $(stat -c %u "$client") == "$(id -u)" && -z $(find "$client" -perm /077 -print) ]]
bash "$(dirname "$0")/ubuntu-inventory.sh" "$legacy"
test "$(git --no-optional-locks -C "$legacy" rev-parse HEAD)" = "$rev"
git --no-optional-locks -C "$legacy" diff --quiet "$rev" -- app bootstrap config database resources routes composer.lock package-lock.json
expected=$(git -C "$legacy" show "$rev:app/Services/BackupService.php" | sha256sum)
actual=$(sha256sum "$legacy/app/Services/BackupService.php")
[[ ${expected%% *} == "${actual%% *}" ]]
[[ -S /run/php/php8.2-fpm.sock && $(php -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;') == 8.2 ]]
/usr/bin/php8.2 -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 2 ? 0 : 1);'
for unit in nginx mysql php8.2-fpm supervisor cron cloudflared; do systemctl is-active --quiet "$unit"; done
[[ $(realpath -e "$legacy/public/storage") == "$(realpath -e "$legacy/storage/app/public")" ]]
[[ ! -e $legacy/storage/framework/down && ! -e $legacy/storage/framework/maintenance.php ]]
[[ ! -L $legacy/public && -z $(find -L "$legacy/.env" -maxdepth 0 -perm /027 -print) ]]
parent=$root
while [[ ! -d $parent ]]; do parent=$(dirname "$parent"); done
df -Pk "$parent" "$legacy/storage"
du -sk "$legacy/storage" "$legacy/vendor" "$legacy/public/build"
# Allowlisted declared values only. A config cache or service environment may override them.
if awk -F= '$1=="TRUSTED_PROXIES" && $2 ~ /\*|REMOTE_ADDR|0\.0\.0\.0\/0|::\/0/ {found=1} END {exit !found}' "$legacy/.env"; then
  echo 'Review the unrestricted proxy declaration before permitting direct LAN access.' >&2
  exit 1
fi
awk -F= '$1 ~ /^(REVERB_(SERVER_)?(HOST|PORT)|VITE_ENABLE_BROADCASTING|SESSION_SECURE_COOKIE)$/ && $2 ~ /^[A-Za-z0-9.:-]+$/ && NF==2 { print "declared",$1,$2 }' "$legacy/.env"
# The site must verify the database and default table mapping privately first.
mysql --defaults-file="$client" --batch < "$(dirname "$0")/ubuntu/queue-inspection.sql"
printf '%s\n' 'Inspection complete. Review external consumers, effective config, restore proof, storage capacity and every ingress privately before cutover.'
