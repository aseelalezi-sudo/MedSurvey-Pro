#!/usr/bin/env bash
# Read-only, run from a reviewed checkout. No application bootstrap or sudo.
set -Eeuo pipefail
here=$(dirname "$(realpath -e "$0")")
legacy=$(realpath -e "${1:?legacy checkout}")
client=${2:-$HOME/.medsurvey-inspection.cnf}
backups=${3:-$legacy/storage/app/backups}
[[ -f $client && ! -L $client && $(stat -c '%u:%a' "$client") == "$(id -u):600" ]]
[[ -f $legacy/artisan ]]
for unit in nginx php8.2-fpm mysql supervisor cron cloudflared; do
  status=$(systemctl show "$unit.service" --property=LoadState,ActiveState) || {
    [[ $status == *LoadState=not-found* ]] || exit 1
  }
  printf '%s %s\n' "$unit" "${status//$'\n'/ }"
done
ss -H -ltn | awk '$4 ~ /:(80|443|6001|8080|18081|3306|33060|20241)$/ {print "listener",$4}'
# Only the database client sees the payload. The server returns aggregates.
mysql --defaults-file="$client" --batch < "$here/queue-inspection.sql"
python3 "$here/latest-backup.py" "$backups"
