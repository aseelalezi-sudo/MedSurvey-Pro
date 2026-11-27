#!/usr/bin/env bash
# Install root-owned under /usr/local/lib/medsurvey; permit these two literal invocations.
set -Eeuo pipefail
source /etc/medsurvey-deploy/site.conf
case ${1:?} in medsurvey-queue|medsurvey-reverb) ;; *) exit 2;; esac
pid=$(/usr/bin/supervisorctl pid "$1")
[[ $pid =~ ^[1-9][0-9]*$ && /proc/$pid/cwd -ef $DEPLOY_ROOT/current ]]
