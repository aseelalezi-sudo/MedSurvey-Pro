#!/usr/bin/env bash
# Read-only. Output is allowlisted; review paths and service names before sharing.
set -eu
export LC_ALL=C
script=$(type -P deploy-medsurvey || :)
if [[ -f $script ]]; then
  printf 'installed %s\n' "$(realpath -e "$script")"
  sha256sum "$script"
  # Print line numbers and operation names only. Never emit commands or arguments.
  awk '/artisan[[:space:]]+down/{print NR,"artisan-down"} /artisan[[:space:]]+up([[:space:]]|$)/{print NR,"artisan-up"} /optimize:clear|cache:clear/{print NR,"cache-clear"} /git[[:space:]]+clean/{print NR,"git-clean"} /storage\/framework/{print NR,"framework-path"}' "$script"
else echo 'installed command not found on this PATH'; fi
if command -v systemctl >/dev/null; then
  systemctl list-units --all --no-pager --no-legend --plain --type=service --type=timer 2>/dev/null |
    awk '$1 ~ /^[A-Za-z0-9_.@:-]+\.(service|timer)$/ {print "unit",$1,$2,$3,$4}' || :
fi
for conf in /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf; do
  [[ -f $conf && -r $conf ]] || continue
  awk '$1=="root" && $2 ~ /^\/[A-Za-z0-9_./-]+;$/ {sub(/;$/,"",$2);print "web-root",$2}
       $1=="fastcgi_pass" && $2 ~ /^unix:\/[A-Za-z0-9_./-]+;$/ {sub(/;$/,"",$2);print "fpm-socket",$2}' "$conf"
done
# Identify non-systemd consumers without printing cron entries or process arguments.
for conf in /etc/cron.d/* /etc/crontab /etc/supervisor/conf.d/*.conf; do
  [[ -f $conf && -r $conf ]] || continue
  awk '/artisan[[:space:]]+(schedule:|queue:|reverb:)/ {print "consumer-file",FILENAME,"line",FNR}' "$conf"
done
crontab -l 2>/dev/null | awk '/artisan[[:space:]]+(schedule:|queue:|reverb:)/ {print "user-crontab match at line",NR}' || :
for proc in /proc/[0-9]*/cmdline; do
  [[ -r $proc ]] || continue
  if grep -azEq '^(queue:work|queue:listen|reverb:start|schedule:work|schedule:run)$' "$proc" 2>/dev/null; then
    cwd=$(readlink "${proc%/*}/cwd" 2>/dev/null || :)
    [[ -z $cwd ]] || printf 'consumer-cwd %s\n' "$cwd"
  fi
done
for project in "$@" "$PWD"; do
  [[ -f $project/artisan ]] || continue
  project=$(realpath -e "$project")
  printf 'project %s\n' "$project"
  git --no-optional-locks -C "$project" rev-parse --verify HEAD 2>/dev/null || :
  for path in public public/storage storage storage/framework/down .env bootstrap/cache/config.php; do
    [[ -e $project/$path ]] && stat -Lc 'mode=%a owner=%U group=%G path=%n' "$project/$path"
  done
  [[ ! -f $project/app/Services/BackupService.php ]] || sha256sum "$project/app/Services/BackupService.php"
done
exit 0
