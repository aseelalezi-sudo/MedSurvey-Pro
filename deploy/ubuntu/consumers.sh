#!/usr/bin/env bash
set -Eeuo pipefail
source /etc/medsurvey-deploy/site.conf
release=$(realpath -e "${2:?release}")
[[ $release -ef "$DEPLOY_ROOT/current" ]]
# Both roles require an explicit site decision. Never discover/start arbitrary groups.
groups=()
for role in REVERB_MODE QUEUE_MODE; do
  case ${!role:?} in
    local) if [[ $role == QUEUE_MODE ]]; then groups+=(medsurvey-queue); else groups+=(medsurvey-reverb); fi;;
    disabled) ;;
    *) echo "Provide a coordinated adapter for the external $role." >&2; exit 1;;
  esac
done
ctl() { sudo -n /usr/bin/supervisorctl "$@"; }
state() {
  local line rc=0
  line=$(ctl status "$1") || rc=$?
  [[ $rc == 0 || $rc == 3 ]] || return 1
  awk -v group="$1" 'NR==1 && $1==group {print $2; found=1} END {exit !found}' <<< "$line"
}
running() {
  [[ $(state "$1") == RUNNING ]]
  sudo -n /usr/local/lib/medsurvey/check-consumer-cwd.sh "$1"
}
ready() { /usr/bin/php8.2 "$(dirname "$0")/check-consumer.php" "$release" "$1"; }
case ${1:?action} in
  check)
    test ! -e "$DEPLOY_ROOT/consumers.paused"
    for group in "${groups[@]}"; do running "$group"; ready "$group"; done;;
  pause)
    touch "$DEPLOY_ROOT/consumers.paused"
    exec 8>>"$DEPLOY_ROOT/scheduler.lock"
    flock -w "${SCHEDULE_DRAIN_SECONDS:-900}" 8
    # Wait for the database worker before stopping its broadcast destination.
    for ((i=${#groups[@]}-1;i>=0;i--)); do
      group=${groups[i]}
      if [[ $(state "$group") != STOPPED ]]; then ctl stop "$group"; fi
      [[ $(state "$group") == STOPPED ]]
    done;;
  resume)
    test -f "$GATE_FILE"
    for group in "${groups[@]}"; do
      if [[ $group == medsurvey-queue ]]; then ready "$group"; fi
      if [[ $(state "$group") == STOPPED ]]; then ctl start "$group"; fi
      running "$group"
      if [[ $group == medsurvey-reverb ]]; then ready "$group"; fi
    done
    rm -f -- "$DEPLOY_ROOT/consumers.paused";;
  *) exit 2;;
esac
