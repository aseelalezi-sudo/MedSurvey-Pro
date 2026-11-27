"""Check the newest finalized MedSurvey SQL backup. Output metadata only."""
import gzip
import hashlib
import os
from pathlib import Path
import re
import stat
import sys
import zlib
from datetime import datetime, timezone


def inspect(directory):
    pattern = re.compile(r"medsurvey_backup_\d{8}_\d{6}(?:_[0-9a-f]{12})?\.sql(?:\.gz)?", re.ASCII)
    candidates = []
    for path in directory.resolve(strict=True).iterdir():
        if pattern.fullmatch(path.name):
            info = path.lstat()
            if stat.S_ISREG(info.st_mode):
                candidates.append((info.st_mtime_ns, path))
    if not candidates:
        raise ValueError("No published SQL backup")

    _, path = max(candidates)
    with os.fdopen(os.open(path, os.O_RDONLY | os.O_NOFOLLOW), "rb") as source:
        before = os.fstat(source.fileno())
        digest = hashlib.sha256()
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
        source.seek(0)
        compressed = path.suffix == ".gz"
        stream = gzip.GzipFile(fileobj=source) if compressed else source
        marker = False
        tail = b""
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            marker |= b"CREATE TABLE" in (tail + chunk).upper()
            tail = chunk[-12:]
        after = os.fstat(source.fileno())
        if not marker or before.st_mtime_ns != after.st_mtime_ns or before.st_size != after.st_size:
            raise ValueError("Changed or unrecognized SQL backup")
    print("backup_path", path)
    print("modified_utc", datetime.fromtimestamp(after.st_mtime, timezone.utc).isoformat())
    print("bytes", after.st_size, "sha256", digest.hexdigest())
    print("format", "gzip CRC checked; SQL marker present" if compressed else "plain SQL marker present")


if __name__ == "__main__":
    try:
        inspect(Path(sys.argv[1]))
    except (OSError, ValueError, EOFError, zlib.error):
        sys.exit("Backup check failed. Verify the effective backup directory, permissions and file integrity privately.")
