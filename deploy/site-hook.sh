#!/usr/bin/env bash
# Install as ROOT/hooks/{check,close,pause,reload,resume,open} (copied files).
set -Eeuo pipefail
hookdir=$(dirname "$(realpath -s "$0")")
site="$hookdir/site.conf"
[[ -f $site && ! -L $site && $(stat -c %u "$site") == 0 && -z $(find "$site" -perm /022 -print) ]]
# Administrator-owned shell configuration and adapters. No service names are inferred.
source "$site"
release=$(realpath -e "${1:?release}")
: "${WEB_UNIT:?}" "${FPM_UNIT:?}" "${TRAFFIC_ADAPTER:?}" "${CONSUMER_ADAPTER:?}"
unit() { [[ $1 =~ ^[A-Za-z0-9_@.:-]+\.service$ && $1 != -* && $(systemctl show -p LoadState --value "$1") == loaded ]]; }
case $(basename "$0") in
  check)
    unit "$WEB_UNIT"; unit "$FPM_UNIT"
    systemctl is-active --quiet "$WEB_UNIT"; systemctl is-active --quiet "$FPM_UNIT"
    "$TRAFFIC_ADAPTER" check "$release"
    "$CONSUMER_ADAPTER" check "$release";;
  close) "$TRAFFIC_ADAPTER" close "$release";; # success requires closed public ingress AND drained requests
  pause) "$CONSUMER_ADAPTER" pause "$release";;
  reload) unit "$FPM_UNIT"; sudo -n systemctl reload "$FPM_UNIT"; systemctl is-active --quiet "$FPM_UNIT";;
  resume) "$CONSUMER_ADAPTER" resume "$release";;
  open) "$TRAFFIC_ADAPTER" open "$release";;
  *) exit 2;;
esac
