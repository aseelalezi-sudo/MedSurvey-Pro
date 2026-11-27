#!/usr/bin/env bash
# Reads the dedicated project's FPM pool status through its independent status listener.
set -Eeuo pipefail
source /etc/medsurvey-deploy/site.conf
test -f "$GATE_FILE"
: "${FPM_STATUS_URL:?}"
# Requests buffered by an old Nginx worker may not have reached FPM yet.
# A graceful reload retires that worker generation; never signal its clients.
master=$(systemctl show --value -p MainPID "$WEB_UNIT")
[[ $master =~ ^[1-9][0-9]*$ ]]
workers=$(ps --ppid "$master" -o pid=,args= | awk '/nginx: worker process/ {print $1}')
[[ -n $workers ]]
sudo -n /usr/bin/systemctl reload "$WEB_UNIT"
deadline=$((SECONDS + ${HTTP_DRAIN_SECONDS:-120}))
for pid in $workers; do
  while [[ -d /proc/$pid ]]; do
    (( SECONDS < deadline )) || { echo 'Old Nginx requests or sockets remain.' >&2; exit 1; }
    sleep 1
  done
done
idle=0
while (( SECONDS < deadline )); do
  report=$(curl -fsS --max-time 4 "$FPM_STATUS_URL")
  if php -r '$s=json_decode(stream_get_contents(STDIN), true); exit(($s["active processes"]??null)===0 && ($s["listen queue"]??null)===0 ? 0 : 1);' <<< "$report"; then
    idle=$((idle + 1))
    if (( idle >= 3 )); then exit 0; fi
  else idle=0; fi
  sleep 1
done
echo 'FPM did not drain; keep all ingress closed.' >&2
exit 1
