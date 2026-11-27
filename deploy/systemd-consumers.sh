#!/usr/bin/env bash
# Called by site-hook.sh. Install beside its trusted site.conf.
set -Eeuo pipefail
source "$(dirname "$(realpath "$0")")/site.conf"
units=()
for role in QUEUE_UNIT REVERB_UNIT SCHEDULER_UNIT; do
  name=${!role:?configure each role, using disabled only after inventory}
  [[ $name != disabled ]] || continue
  [[ $name =~ ^[A-Za-z0-9_@.:-]+\.(service|timer)$ && $name != -* ]]
  [[ $(systemctl show -p LoadState --value "$name") == loaded ]]
  units+=("$name")
done
case ${1:?} in
  check) for name in "${units[@]}"; do systemctl is-active --quiet "$name"; done;;
  pause) for name in "${units[@]}"; do sudo -n systemctl stop "$name"; [[ $(systemctl show -p ActiveState --value "$name") == inactive ]]; done;;
  resume) for name in "${units[@]}"; do sudo -n systemctl start "$name"; systemctl is-active --quiet "$name"; done;;
  *) exit 2;;
esac
