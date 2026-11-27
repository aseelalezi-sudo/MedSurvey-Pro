#!/usr/bin/env bash
# The sole project cron entry runs this file as the deployment user.
set -Eeuo pipefail
umask 027
source /etc/medsurvey-deploy/site.conf
exec 8>>"$DEPLOY_ROOT/scheduler.lock"
flock -n -E 73 8 || { rc=$?; [[ $rc == 73 ]] && exit 0; exit "$rc"; }
[[ ! -e $GATE_FILE && ! -e $DEPLOY_ROOT/consumers.paused ]] || exit 0
cd "$DEPLOY_ROOT/current"
exec /usr/bin/php8.2 artisan schedule:run --no-interaction
