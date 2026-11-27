# Existing Ubuntu installation

For a PHP 8.2 host using a deployment user's cron and optional Supervisor workers, use the [Ubuntu cutover profile and read-only preflight](ubuntu-supervisor-cutover.md).

## Inventory and approvals

Run the reviewed script from the PR checkout as the deployment user. Supply the absolute legacy checkout if known, or run it from the existing project directory. Review its output on the host. It emits paths, file modes, checksums, matching operation line numbers and service names. It does not source the deployment command, load Laravel, print process arguments, read .env contents, or export log records.

```bash
bash /path/to/reviewed/deploy/ubuntu-inventory.sh "$PWD"
```

Cross-check the site vhost, included Nginx files, FPM pool/socket and OS identity, cron for the actual service user, supervisor groups, systemd units/timers and timer-triggered jobs with the administrator. A process absent during inventory can still be scheduled. Record the public vhost and a loopback-only read-only origin probe for **this** site. Compare the installed controller and BackupService checksums to the Git deployment record. Preserve the original command, service definitions, vhost and approved commit in private off-host recovery storage. Inventory alone cannot explain an earlier maintenance event. Correlate the deployment timestamp with down/up exit statuses, maintenance driver, active release path, shared storage and any cache clear before attributing a cause.

## Prepare the site

1. At an approved window close public ingress, confirm it is closed on every public entry path, drain active requests and stop consumers. Freeze the legacy deploy command. Enable file-based Laravel maintenance on the legacy release. Use its existing MySQL database, uploads, application key, queue and session configuration. Verify protected off-host copies of SQL, public/private uploads, environment and service configuration, and rehearse a restore first.
2. Provision a new absolute `ROOT` outside all served roots and outside the legacy checkout. Create `repo` with the approved old and new commits, `releases`, `hooks`, `shared` and a deployment-account-owned mode 0700 `recovery`. Keep the lock, current symlink and releases on the same filesystem. The deployment user owns the release control paths. Restrict group access to traversal/read where needed and use a setgid release directory to inherit the intended web group. The web user needs traversal and read access to release code and the shared environment, plus write access to the designated Laravel runtime directories. Keep owners and modes of legacy uploads. Verify permissions as the actual FPM user and the Nginx user. Check `namei -l` on the effective paths. Never make the tree world-writable. Keep hook files, their config, parent directories, the installed controller and shortcut protected from edits by either web process. Delegate only the specific service actions required by the site.
3. With writes frozen, run `bash /path/to/reviewed/deploy/adopt-legacy.sh "$ROOT" "$LEGACY" "$LEGACY_SHA"`. Supply the full verified legacy commit. It stages a bootstrap release, reuses the installed vendor and built public assets, and links to the original environment and storage. It compares effective key and database configuration before publishing the initial symlink. Existing storage and its original path remain necessary until a separately approved data relocation. Review any local patches, custom public assets and site-specific ignored files before running it. A failed preparation leaves a private staging directory for inspection. Reattempt only after checking the unpublished staging and links.
4. Serve only `ROOT/current/public`. Route PHP to the resolved release (for Nginx/FPM use the realpath document root), disable directory indexes, deny hidden files outside the required ACME path, and prohibit PHP execution in uploaded or built assets. Verify all vhosts and aliases, including legacy hosts, so only the intended public upload directory is served. In staging put harmless random canaries in recovery, private storage and a sibling of .env, verify those names return 403/404 and a public canary succeeds; remove just the canaries. Keep actual keys, SQL and user files out of HTTP tests. Verify that rolls between releases switch FPM code, uploads and the readiness target together.
5. Preserve the old vhost configuration. Test the new vhost privately while the public gate is closed, reload the verified FPM service, run `artisan up` on the bootstrap release behind the gate, check the private origin and a fixture login, resume the inventoried consumers from `current`, then reopen ingress. This ends the **layout-only** cutover on the original code. A separate release deployment follows. If layout cutover fails, keep ingress closed, repoint the vhost and consumer working paths to the preserved legacy directory, verify config, reload FPM, release owned Laravel maintenance, probe the original application, resume consumers and finally open the gate. Both paths point to the same data. Keep the old command disabled until its assumptions are valid again.

## Hooks and short command

Install six individual executable copies of `deploy/site-hook.sh` as `ROOT/hooks/check`, `close`, `pause`, `reload`, `resume` and `open`. Put reviewed copies of `nginx-traffic.sh` and, for a fully systemd-managed site, `systemd-consumers.sh` beside the root-owned `site.conf`. Use mode 0750 or 0755 for scripts and 0640 for config, with group read as needed. Configuration is administrator-controlled shell syntax. Resolve the following fields from inventory.

| Field | Required site evidence |
|---|---|
| WEB_UNIT, FPM_UNIT | Exact loaded service names and the FPM pool serving this site |
| QUEUE_UNIT, REVERB_UNIT, SCHEDULER_UNIT | Dedicated service/timer per role, or explicit `disabled` after checking cron, supervisor, systemd and containers |
| TRAFFIC_ADAPTER, CONSUMER_ADAPTER | Absolute reviewed executable paths. Supply a verified site-specific consumer adapter if supervisor, cron, timers with running one-shot jobs, multiple workers, or containers need coordinated handling |
| GATE_FILE, PUBLIC_HEALTH_URL | Dedicated non-serving flag path, plus this site's public ingress probe; include every alternate entrance in the gate |
| HEALTH_URL, DRAIN_CHECK | Loopback/private origin ending in /api/health, reachable only by the operator, and a site-specific executable that verifies in-flight work drained and returns nonzero on timeout |
| BACKUP_ROOT, MIN_FREE_KIB | Canonical private SQL destination matching the **legacy effective settings**, plus sufficient capacity on each data, SQL and releases filesystem |

For the supplied Nginx adapter configure the **public** server blocks to return exactly `503` with body `medsurvey-maintenance` when the flag exists, e.g. `if (-f /actual/private/gate) { return 503 "medsurvey-maintenance"; }`. The private origin uses the same release and FPM pool, binds on loopback, and excludes this external gate. A request header, public hostname or client-supplied bypass is unsuitable. Verify the Nginx process can stat the flag, validate configuration before reloading, and prove public blocking and private probing independently. The supplied adapter closes intake and calls the site's drain checker. On sites with multiple entry points or a load balancer, implement the same check/close/open contract for all entry points. Consumers must stop in-flight writes before reporting pause success, including already running scheduled jobs.

Install a pinned copy of the release controller outside the checkout. Preserve the original short command separately and install `deploy/deploy-shortcut.sh` as `deploy-medsurvey` on the intended PATH. Configure root-owned `/etc/medsurvey-deploy/site.conf` with `DEPLOY_ROOT`, `RELEASE_CONTROLLER`, `HEALTH_URL` and approved capacity/backup settings. Put the reviewed 40-character SHA and newline in a protected `ROOT/approved-commit` by atomic rename. Confirm `type -a deploy-medsurvey` as the intended user. The shortcut takes no arguments. The pinned controller and hooks must be updated separately whenever their interface changes.

## Deploy and recover

Run `deploy-medsurvey` under the deployment account after approval. Verify the private record in `ROOT/recovery`, the active release, protected pages and service states. The gate remains closed through the internal health probe and consumer resume. After a partial failure inspect both gate state and service state explicitly. Where recovery is inconclusive, leave ingress closed.

For a compatible manual rollback, hold the **same** lock for the whole operation (`exec 9>>"$ROOT/deploy.lock"; flock -n 9` in a dedicated operator shell). Read the private record's previous and release files; confirm that current resolves to the failed release and previous is an existing, unmodified direct child of releases. Close and drain, enter maintenance, pause consumers, create a fresh private symlink to previous on the same filesystem and atomically rename it over current using `mv -Tf`. Reload FPM, run `artisan up` behind the gate, check the private database-backed endpoint and fixture workflow, resume consumers and finally open ingress. Release the lock when done. After schema changes prove backward compatibility before switching. Otherwise repair forward or get explicit authorization for a coordinated SQL **and uploads** restore after rehearsing it on a separate database. Restoring can discard changes made after the backup.

## Retention

Run the cleanup review under the deploy lock. Inventory disk space on every relevant filesystem. Keep current, the bootstrap and original legacy trees during transition, the last two known-good releases, any pinned incident release, and both paths named by every retained recovery record. Treat incomplete or unclear records as pinned. Keep recovery records for at least the site's approved restore window (initial suggestion 30 days), extending it for incidents and legal holds. Verify an encrypted off-host copy and restore proof before expiring a record. Never prune shared storage, environment, application SQL directory, symlink targets or uploads as part of release cleanup. First list exact canonical direct-child candidates with sizes/dates, obtain operator approval and remove only reviewed entries. Remove old immutable assets only when absent from **all** retained release manifests and built asset trees and after the site's longest supported offline-client/update window. If that window has no bound, retain the assets. Budget space for private archives, staged releases and failed attempts separately; stop before deployment if capacity is insufficient. Application SQL retention runs independently.

## Android acceptance

Use isolated accounts A and B in different facilities, distinguishable surveys, the same APK against both approved API versions, and a test HTTPS endpoint. Capture build SHA, device/OS, API revision and pass/fail, without credentials or patient information.

1. Install the debug APK on the designated QA device. Sign in as A. Confirm expected surveys, permitted actions, Arabic form layout and API version handshake.
2. Submit online, then enter airplane mode, record a second answer, restart the app and reconnect. Verify exactly one server record after retries and a cleared pending queue.
3. Sign out and sign in as B, also after an offline restart. Confirm A's surveys, draft, queued answers and cached identity are inaccessible. Check B's permissions and revoke the session on logout.
4. Use controlled version metadata with a later offered build and minimum at or below the installed build. Dismiss the optional update and continue. Raise the minimum above the installed build and confirm the mandatory prompt blocks entry, including after restart. Restore fixture settings.
5. Repeat with the new API, verify denied cross-facility requests and permitted submission as the intended account, and record any device-specific failures before sign-off.

## Reproduce local load measurements

Build each provided Docker image from a clean Git archive using its own Dockerfile. Run the following with Python 3 and Docker in a dedicated development environment. It creates disposable named containers and a new-only fixture database, binds only to loopback, and writes aggregate metrics. The same browser/API accounts and 12,000 synthetic rows are used for each comparison. The sequential cold-to-warm schedule alternates version order across three rounds. Each steady phase lasts 30 seconds. CPU is time consumed over the phase; memory is a phase-end sample. Keep image IDs and host workload alongside results.

```text
python tests/performance/acceptance.py BASE_IMAGE CANDIDATE_IMAGE /private/local/results.json
```
