# Field acceptance and offline recovery

Run from the approved review checkout. This supplement covers the outstanding evidence after the initial Ubuntu inventory. Site observations, credentials and real recovery records belong in private operator storage.

## Single read-only field command

```bash
bash deploy/ubuntu/field-audit.sh "$LEGACY"
```

Run as the deployment user. The defaults are its existing 0600 `$HOME/.medsurvey-inspection.cnf` with the application database and a SELECT-only account, and `$LEGACY/storage/app/backups`. Supply the option file and effective private backup directory as the second and third arguments if different. Run locally on the VM. The script reports unit states and listening addresses, queries MySQL in a read-only transaction, and fully reads the latest finalized project SQL file by modification time. For gzip it checks the CRC through EOF. For both formats it reports the private path, byte size, modification time, SHA-256 and presence of a schema marker. An independently stored digest and an import provide stronger evidence. Retain output locally.

Classification uses the database queue's JSON envelope. Laravel's BroadcastEvent records the event class in displayName. A match requires both the known wrapper and event. Other declared broadcasters, mail, notifications, unknown command fingerprints and malformed envelopes remain distinguishable. The SQL never selects the serialized command into the client. Each row of output is a group with count, attempt count, reservation state, eligibility and age range in seconds. Retry expiry uses this profile's 90-second configuration. Totals include all queues; the detailed groups cover default only.

Keep default and failed jobs untouched. If all old records classify as transient survey broadcasts, retain an encrypted point-in-time copy and obtain a separate decision to expire precisely those stale records without sending them. Unknown classes require a private producer/owner review. Check other hosts, service inventories and database access owners for shared consumers. Local process counts and a loopback database listener cannot exclude forwarded or remotely scheduled work. Use one worker subscribed only to a verified empty medsurvey-live after approval. If the local application has no live delivery requirement or external subscriber, approve disabling broadcasting in the candidate and keep the new services stopped.

## Ingress evidence at the approved window

Use the existing public gate in every serving host block, including direct IP/LAN access. Keep the private health vhost on 127.0.0.1:18081 outside tunnel ingress; the tunnel's local metrics port is separate. Public TLS may terminate at the tunnel edge while Nginx serves HTTP locally. Trust only verified proxy peers. Keep direct LAN HTTP on its own approved IP/name until internal TLS is ready. Use the published Nginx client WebSocket route and private app publishing connection, loopback Reverb binding and exact origin allowlist in the existing profile.

Populate a Bash array ORIGINS with every real external and LAN base URL in private site configuration. Exercise the public path from each corresponding network. During the **closed** gate window the following checks use the known marker. Run the private probe after Laravel has been brought up behind the gate. Session login/logout checks use controlled test accounts.

```bash
for origin in "${ORIGINS[@]}"; do
  for uri in /api/health /login /app/maintenance-check; do
    test "$(curl -sS --max-time 12 -w ' %{http_code}' "$origin$uri")" = 'medsurvey-maintenance 503' || exit 1
  done
done
curl -fsS --max-time 10 http://127.0.0.1:18081/api/health |
  php -r '$data=json_decode(stream_get_contents(STDIN),true); exit(($data["status"]??null)==="ok"?0:1);'
```

Use the [cutover and rollback sequence](ubuntu-supervisor-cutover.md#approval-transition-and-rollback) under the deployment lock where specified. When recording session evidence, retain only Secure, HttpOnly and SameSite flags. Exclude cookie values, tunnel tokens and application configuration dumps.

## Encrypted recovery point

At an approved, quiesced recovery point collect the project's SQL backup and a public/private uploads archive from the same point. A prior daily SQL file alone covers the database. Capture the count inventory from the same frozen state or a matching consistent replica snapshot. A comparison to a later live database can report legitimate changes as mismatches.

```bash
umask 077
mysql --defaults-file="$HOME/.medsurvey-inspection.cnf" --batch --skip-column-names \
  < deploy/ubuntu/source-counts.sql > "$PRIVATE_RECORD/counts.json"
```

A release recovery record already contains a single SQL file, uploads.tar.gz and SHA256SUMS. For an approved manual recovery point use the verified SQL file and archive both upload directories from the frozen storage using `tar -czf "$BUNDLE/uploads.tar.gz" -C "$LEGACY/storage/app" public private`. Keep the bundle in a new private directory outside served paths. From inside it compute `sha256sum "$SQL_BASENAME" uploads.tar.gz > SHA256SUMS`. Authenticate and retain this manifest and count inventory independently. Escrow the original APP_KEY and necessary environment secrets in a separate, access-controlled recovery vault. Validate key recovery by comparing its fingerprint privately. No key is needed to load SQL into the rehearsal instance.

Use an approved remote encrypted restic repository with pre-provisioned append-only backup access, pinned SSH host identity for SFTP, and independently escrowed repository password. Provide RESTIC_REPOSITORY_FILE and RESTIC_PASSWORD_FILE through protected operator configuration. The following commands copy only the approved recovery artifacts. Execute them on the backup host after approval and retain the snapshot ID. Run verification and retention under a separate recovery identity. Keep the protected transition snapshot and existing backups under the approved retention policy.

```bash
(cd "$BUNDLE" && restic backup --tag medsurvey-recovery "$SQL_BASENAME" uploads.tar.gz SHA256SUMS)
restic check --read-data
restic restore "$APPROVED_SNAPSHOT_ID" --target "$NEW_PRIVATE_RECOVERY_DIRECTORY"
```

Store counts.json with the independent evidence. Verify the recovered SHA256SUMS against that evidence before the drill. Keep raw backups, filenames of individual uploads, keys, and reports outside CI. [Restic backup](https://restic.readthedocs.io/en/stable/040_backup.html) · [Repository checks](https://restic.readthedocs.io/en/stable/045_working_with_repos.html)

## Restore drill on the disconnected recovery VM

Stage the reviewed code including its locked PHP dependencies, PHP CLI, Python 3.11+, a local Docker Engine and a verified local mysql:8.0 image **before** disconnecting the recovery VM. Use encrypted disks for temporary files, Docker volumes and any swap. Allow space for uncompressed SQL data and uploads. Provide the private decrypted bundle and authenticated counts file. Run only after the administrator approves processing the recovered data on this VM.

```bash
python3 deploy/ubuntu/restore-drill.py "$BUNDLE" --counts "$PRIVATE_RECORD/counts.json"
```

The drill creates a fresh MySQL container on the local Unix socket with networking disabled, no published ports, no host mounts and MySQL events disabled. It streams the dump into a schema-limited account using the internal socket. It never starts the app, scheduler, mail, queue or Reverb. It checks exact table counts, restored foreign keys, consistency of survey-response tenant ownership, and the application's actual facility-scoped response query for two represented facilities. A single-facility restore reports insufficient evidence for the two-facility check. It restores regular files in the two upload roots privately, verifies their bytes and rejects links or unexpected paths. The archive checksum ties the files to the authenticated source. It removes its temporary directory, container and anonymous volumes after the drill. Require matched source counts and the appropriate tenant proof before signing off. Missing historical counts should be recorded as unavailable; use a fresh recovery point with known counts for full acceptance.

The [physical Android acceptance checklist](ubuntu-release-operations.md#android-acceptance) covers distinct facilities, offline retries, logout, local state and app updates. Record device and build privately.
