#!/usr/bin/env bash
# Install outside the web root as deploy-medsurvey. Administrator-owned config.
set -Eeuo pipefail
[[ $# == 0 ]]
config=/etc/medsurvey-deploy/site.conf
[[ -f $config && ! -L $config && $(stat -c %u "$config") == 0 && -z $(find "$config" -perm /022 -print) ]]
source "$config"
: "${DEPLOY_ROOT:?}" "${RELEASE_CONTROLLER:?}" "${HEALTH_URL:?}"
if [[ -n ${PHP_CLI:-} ]]; then
  [[ $(command -v php) -ef $PHP_CLI ]]
fi
read -r commit < "$DEPLOY_ROOT/approved-commit"
[[ $commit =~ ^[a-f0-9]{40}$ ]]
export HEALTH_URL
export MIN_FREE_KIB BACKUP_ROOT
exec "$RELEASE_CONTROLLER" "$DEPLOY_ROOT" "$commit"
