#!/usr/bin/env bash
# Nginx must implement the public maintenance marker and a separate loopback vhost.
set -Eeuo pipefail
source "$(dirname "$(realpath "$0")")/site.conf"
: "${GATE_FILE:?}" "${PUBLIC_HEALTH_URL:?}" "${HEALTH_URL:?}" "${DRAIN_CHECK:?}"
[[ $PUBLIC_HEALTH_URL != "$HEALTH_URL" && $GATE_FILE == /* && ! -L $GATE_FILE ]]
case ${1:?} in
  check)
    test ! -e "$GATE_FILE"; test -w "$(dirname "$GATE_FILE")"; test -x "$DRAIN_CHECK";;
  close)
    touch "$GATE_FILE"
    result=$(curl -sS --max-time 10 -w ' %{http_code}' "$PUBLIC_HEALTH_URL")
    [[ $result == 'medsurvey-maintenance 503' ]]
    "$DRAIN_CHECK" "${2:?release}";;
  open)
    rm -f -- "$GATE_FILE"
    curl -fsS --max-time 10 "$PUBLIC_HEALTH_URL" |
      php -r '$v=json_decode(stream_get_contents(STDIN),true);exit(($v["status"]??null)==="ok"?0:1);';;
  *) exit 2;;
esac
