#!/usr/bin/env bash
# Nginx must implement the public maintenance marker and a separate loopback vhost.
set -Eeuo pipefail
source "$(dirname "$(realpath "$0")")/site.conf"
: "${GATE_FILE:?}" "${PUBLIC_HEALTH_URL:?}" "${HEALTH_URL:?}" "${DRAIN_CHECK:?}"
[[ $PUBLIC_HEALTH_URL != "$HEALTH_URL" && $GATE_FILE == /* && ! -L $GATE_FILE ]]
urls=("$PUBLIC_HEALTH_URL" "${ADDITIONAL_HEALTH_URLS[@]}")
for url in "${urls[@]}"; do [[ $url != "$HEALTH_URL" ]]; done
case ${1:?} in
  check)
    test ! -e "$GATE_FILE"; test -w "$(dirname "$GATE_FILE")"; test -x "$DRAIN_CHECK";;
  close)
    touch "$GATE_FILE"
    for url in "${urls[@]}"; do
      result=$(curl -sS --max-time 10 -w ' %{http_code}' "$url")
      [[ $result == 'medsurvey-maintenance 503' ]]
    done
    "$DRAIN_CHECK" "${2:?release}";;
  open)
    rm -f -- "$GATE_FILE"
    trap 'touch "$GATE_FILE"' ERR
    for url in "${urls[@]}"; do
      curl -fsS --max-time 10 "$url" |
        php -r '$v=json_decode(stream_get_contents(STDIN),true);exit(($v["status"]??null)==="ok"?0:1);'
    done;;
  *) exit 2;;
esac
