"""Restore a private recovery bundle on an offline Linux rehearsal host."""
import argparse
from collections import defaultdict
import gzip
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import time
import zlib

DOCKER = ["docker", "--host=unix:///var/run/docker.sock"]
MYSQL = Path(__file__).with_name("mysql-recovery-image.txt").read_text().strip()
DUMP = re.compile(r"medsurvey_backup_\d{8}_\d{6}(?:_[a-f0-9]{12})?\.sql(?:\.gz)?", re.ASCII)


def output(command, data=None):
    return subprocess.run(command, input=data, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                          check=True).stdout.decode().strip()


def digest(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def inputs(bundle):
    dumps = [p for p in bundle.iterdir() if DUMP.fullmatch(p.name)]
    if len(dumps) != 1:
        raise ValueError("expected one SQL backup")
    dump = dumps[0]
    uploads = bundle / "uploads.tar.gz"
    manifest = bundle / "SHA256SUMS"
    for path in (dump, uploads, manifest):
        if path.is_symlink() or not path.is_file():
            raise ValueError("missing recovery evidence")
    # Compare exact basenames against the independently authenticated manifest.
    entries = set(manifest.read_text().splitlines())
    for path in (dump, uploads):
        if f"{digest(path)}  {path.name}" not in entries:
            raise ValueError("checksum mismatch")
    return dump, uploads


def restore_uploads(archive, target):
    roots = set()
    files = 0
    with tarfile.open(archive, "r:gz") as tar:
        for member in tar:
            path = PurePosixPath(member.name)
            if not path.parts or path.parts[0] not in ("public", "private") or ".." in path.parts:
                raise ValueError("unexpected upload archive path")
            destination = target / path
            if member.isdir():
                destination.mkdir(mode=0o700, exist_ok=True, parents=True)
                roots.add(path.parts[0])
            elif member.isfile() and len(path.parts) > 1:
                destination.parent.mkdir(mode=0o700, exist_ok=True, parents=True)
                expected = hashlib.sha256()
                with tar.extractfile(member) as stream, destination.open("xb") as restored:
                    for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                        expected.update(chunk)
                        restored.write(chunk)
                if digest(destination) != expected.hexdigest():
                    raise ValueError("upload mismatch")
                files += 1
            else:
                raise ValueError("unsupported upload archive entry")
    if roots != {"public", "private"}:
        raise ValueError("incomplete upload roots")
    return files


def identifier(name):
    if not re.fullmatch(r"[A-Za-z0-9_]+", name):
        raise ValueError("unexpected schema identifier")
    return f"`{name}`"


def verify_rows(query, baseline):
    tables = query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() "
                   "AND table_type='BASE TABLE'").splitlines()
    if not {"tenants", "surveys", "survey_responses", "users"}.issubset(tables):
        raise ValueError("incomplete application schema")
    counts = {t: int(query(f"SELECT COUNT(*) FROM {identifier(t)}")) for t in tables}
    if baseline is not None and counts != baseline:
        raise ValueError("row count mismatch")

    keys = defaultdict(list)
    for line in query("SELECT table_name,constraint_name,column_name,referenced_table_name,referenced_column_name "
                      "FROM information_schema.key_column_usage WHERE table_schema=DATABASE() "
                      "AND referenced_table_schema=DATABASE() ORDER BY ordinal_position").splitlines():
        child, key, column, parent, ref = line.split("\t")
        keys[(child, key, parent)].append((identifier(column), identifier(ref)))
    if not keys:
        raise ValueError("missing foreign keys")
    for (child, _, parent), pairs in keys.items():
        join = " AND ".join(f"c.{col}=p.{ref}" for col, ref in pairs)
        present = " AND ".join(f"c.{col} IS NOT NULL" for col, _ in pairs)
        if int(query(f"SELECT COUNT(*) FROM {identifier(child)} c LEFT JOIN {identifier(parent)} p "
                     f"ON {join} WHERE {present} AND p.{pairs[0][1]} IS NULL")):
            raise ValueError("orphaned reference")
    # Global surveys can receive responses from different facilities.
    if int(query("SELECT COUNT(*) FROM survey_responses r JOIN surveys s ON s.id=r.surveyId "
                 "WHERE s.tenantId IS NOT NULL AND NOT (s.tenantId <=> r.tenantId)")):
        raise ValueError("inconsistent tenant assignment")
    print(f"tables={len(tables)} rows={sum(counts.values())} verified_foreign_keys={len(keys)} tenant_mismatches=0")
    print("source_row_counts=" + ("matched" if baseline is not None else "unavailable"))
    scope = output(["php", str(Path(__file__).with_name("response-scope.php"))])
    scoped_count = ("SELECT COUNT(*), COUNT(CASE WHEN NOT (s.tenantId <=> @facility) THEN 1 END) "
                    f"INTO @seen,@foreign_rows FROM ({scope}) s")
    represented = int(query("SELECT COUNT(DISTINCT tenantId) FROM survey_responses"))
    for offset in range(min(2, represented)):
        result = query("SET @facility=(SELECT DISTINCT tenantId FROM survey_responses WHERE tenantId IS NOT NULL "
                       f"ORDER BY tenantId LIMIT 1 OFFSET {offset}); "
                       f"SET @scope=CONVERT(0x{scoped_count.encode().hex()} USING utf8mb4); PREPARE scoped FROM @scope; "
                       "EXECUTE scoped USING @facility; DEALLOCATE PREPARE scoped; "
                       "SELECT @foreign_rows=0 AND @seen>0 AND @seen=(SELECT COUNT(*) FROM survey_responses "
                       "WHERE tenantId=@facility)")
        if result != "1":
            raise ValueError("application scope mismatch")
    print("two_facility_application_scope=" + ("verified" if represented >= 2 else "insufficient restored tenants"))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("bundle", type=Path)
    parser.add_argument("--counts", type=Path, help="independent same-point table count JSON")
    args = parser.parse_args()
    phase = "verifying bundle digests"
    container = None
    try:
        dump, uploads = inputs(args.bundle)
        baseline = json.loads(args.counts.read_text()) if args.counts else None
        if args.counts and not isinstance(baseline, dict):
            raise ValueError("invalid count inventory")
        phase = "restoring uploads"
        with tempfile.TemporaryDirectory(prefix="medsurvey-drill-") as temp:
            files = restore_uploads(uploads, Path(temp))
            print(f"verified_upload_files={files}")
        phase = "starting isolated MySQL"
        container = output(DOCKER + ["run", "--pull=never", "--network=none", "--log-driver=none", "-d",
                                     "-e", "MYSQL_ALLOW_EMPTY_PASSWORD=yes", MYSQL,
                                     "--skip-networking", "--skip-log-bin", "--event-scheduler=DISABLED",
                                     "--local-infile=OFF"])
        exec_cmd = DOCKER + ["exec", "-i", container]
        mysql = ["mysql", "--no-defaults", "--protocol=socket", "--batch", "--skip-column-names"]
        for _ in range(120):
            # The image uses a temporary server during initialization.
            if output(exec_cmd + ["cat", "/proc/1/comm"]) == "mysqld":
                try:
                    output(exec_cmd + mysql + ["-uroot"], b"SELECT 1;")
                    break
                except subprocess.CalledProcessError:
                    pass
            time.sleep(1)
        else:
            raise ValueError("startup timeout")
        if output(exec_cmd + mysql + ["-uroot"], b"SELECT VERSION();") != "8.0.46":
            raise ValueError("recovery server version mismatch")
        output(exec_cmd + mysql + ["-uroot"], b"CREATE DATABASE drill; CREATE USER 'drill'@'localhost'; "
               b"GRANT ALL ON drill.* TO 'drill'@'localhost';")
        client = exec_cmd + mysql + ["--binary-mode=1", "--local-infile=0", "-udrill", "drill"]
        phase = "importing SQL"
        opener = gzip.open if dump.suffix == ".gz" else open
        with opener(dump, "rb") as source, subprocess.Popen(client, stdin=subprocess.PIPE,
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL) as proc:
            with proc.stdin:
                shutil.copyfileobj(source, proc.stdin)
            if proc.wait() != 0:
                raise ValueError("import failed")
        phase = "checking tenant relationships, foreign keys and table counts"
        verify_rows(lambda sql: output(client, sql.encode()), baseline)
    except (OSError, ValueError, EOFError, zlib.error, tarfile.TarError, subprocess.SubprocessError):
        sys.exit(f"Drill failed while {phase}. No record contents are reported.")
    finally:
        if container:
            # Remove only the container ID returned by this run and its anonymous volumes.
            output(DOCKER + ["rm", "-f", "-v", container])


if __name__ == "__main__":
    main()
