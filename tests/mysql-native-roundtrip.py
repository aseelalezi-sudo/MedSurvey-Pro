"""Exercise the schema, native MySQL tools and restore path against a disposable loopback fixture."""
import argparse
from concurrent.futures import ThreadPoolExecutor
import os
from pathlib import Path
import re
import secrets
import shlex
import subprocess
import sys
import tempfile
from threading import Barrier
import time

ROOT = Path(__file__).resolve().parents[1]


def docker(*args, **options):
    return subprocess.check_output(["docker", *args], **options)


def verify_php(script, marker, env):
    result = subprocess.run(["php", script], env=env, cwd=ROOT, stdout=subprocess.PIPE, check=True)
    sys.stdout.buffer.write(result.stdout)
    sys.stdout.buffer.flush()
    if marker.encode() not in result.stdout:
        raise RuntimeError(f"{script} did not complete its assertions")


def verify_concurrent_submissions(admin, container, password):
    admin("USE schema_cycle_test; INSERT INTO tenants(id,name) VALUES ('race-tenant','Fixture'); "
          "INSERT INTO surveys(id,title,description,tenantId) VALUES ('race-survey','Fixture','','race-tenant');")
    barrier = Barrier(8)

    def submit(index):
        barrier.wait()
        query = ("INSERT INTO survey_responses(id,clientResponseId,surveyId,tenantId,department,overallScore,answers) "
                 f"VALUES ('race-{index}','one-client-id','race-survey','race-tenant','Fixture',90,'[]');")
        proc = subprocess.run(["docker", "exec", "-i", "-e", "MYSQL_PWD", container, "mysql", "--protocol=tcp",
                               "-h127.0.0.1", "-ufixture", "schema_cycle_test"], input=query.encode(),
                              capture_output=True, env=dict(os.environ, MYSQL_PWD=password))
        if proc.returncode == 0:
            return "inserted"
        if b"1062" in proc.stderr:
            return "duplicate"
        raise RuntimeError("The concurrent write failed unexpectedly")

    with ThreadPoolExecutor(max_workers=8) as executor:
        results = list(executor.map(submit, range(8)))
    assert results.count("inserted") == 1 and results.count("duplicate") == 7
    assert admin("SELECT COUNT(*) FROM schema_cycle_test.survey_responses WHERE clientResponseId='one-client-id'") == "1"
    print("PASS: concurrent inserts on one client response ID produced one row and seven duplicate rejections")


def make_wrapper(directory, tool, container, incompatible=False):
    program = directory / (tool + ("-old" if incompatible else "") + ".py")
    program.write_text("""import os, subprocess, sys
args = sys.argv[1:]
if INCOMPATIBLE:
    if args[-1:] != ['--version']:
        raise SystemExit('The mismatched tool was invoked for SQL')
    print('mysqldump Ver 10.19 Distrib 10.4.32-MariaDB')
    raise SystemExit(0)
result = next((x.split('=', 1)[1] for x in args if x.startswith('--result-file=')), None)
skip = ('--protocol=', '--host=', '--port=', '--result-file=')
args = [x for x in args if not x.startswith(skip)]
docker = ['docker', 'exec', '-i', '--env', 'MYSQL_PWD', CONTAINER, TOOL]
if '--version' not in args:
    args[2:2] = ['--protocol=tcp', '--host=127.0.0.1']
if result:
    with open(result, 'wb') as destination:
        run = subprocess.run(docker + args, stdout=destination, stderr=subprocess.PIPE)
    if run.returncode:
        with open(__file__ + '.error', 'wb') as error:
            error.write(run.stderr)
        raise SystemExit(run.returncode)
else:
    subprocess.run(docker + args, check=True)
""".replace("INCOMPATIBLE", repr(incompatible)).replace("CONTAINER", repr(container)).replace("TOOL", repr(tool)),
                       encoding="utf-8")
    if os.name == "nt":
        path = program.with_suffix(".cmd")
        path.write_text(f'@"{sys.executable}" "{program}" %*\n')
    else:
        path = program.with_suffix(".sh")
        path.write_text("#!/bin/sh\nexec " + shlex.join([sys.executable, str(program)]) + ' "$@"\n')
        path.chmod(0o700)
    return str(path)


def run(image, full_suite):
    version = image.split("@")[0].split(":")[-1]
    if version not in {"8.0.46", "8.4.13"} or not image.startswith("mysql:"):
        raise ValueError("An exact MySQL fixture image is required")
    auth = dict(os.environ, MYSQL_ROOT_PASSWORD=secrets.token_hex(20), MYSQL_PASSWORD=secrets.token_hex(20))
    container = docker("run", "--pull=never", "-d", "--label", "medsurvey.fixture=backup-cycle",
                       "--publish", "127.0.0.1::3306", "-e", "MYSQL_ROOT_PASSWORD", "-e", "MYSQL_PASSWORD",
                       "-e", "MYSQL_USER=fixture", "-e", "MYSQL_DATABASE=schema_cycle_test", image,
                       "--event-scheduler=DISABLED", "--local-infile=OFF", env=auth).decode().strip()
    try:
        def admin(sql):
            return docker("exec", "-i", "-e", "MYSQL_PWD", container, "mysql", "--protocol=tcp",
                          "-h127.0.0.1", "-uroot", "-BN", input=sql.encode(),
                          env=dict(os.environ, MYSQL_PWD=auth["MYSQL_ROOT_PASSWORD"]), stderr=subprocess.DEVNULL).decode().strip()

        for _ in range(120):
            try:
                identity = admin("SELECT VERSION(), @@server_uuid")
                break
            except subprocess.CalledProcessError:
                time.sleep(1)
        else:
            raise RuntimeError("MySQL did not become ready")
        actual, uuid = identity.split("\t")
        assert actual == version
        endpoint = docker("port", container, "3306/tcp").decode().strip()
        assert re.fullmatch(r"127\.0\.0\.1:[0-9]+", endpoint) and not endpoint.endswith(":3306")
        port = endpoint.rsplit(":", 1)[1]
        admin("CREATE DATABASE restore_cycle_test; GRANT ALL ON restore_cycle_test.* TO 'fixture'@'%'; "
              "CREATE DATABASE parity_test; GRANT ALL ON parity_test.* TO 'fixture'@'%';")
        with tempfile.TemporaryDirectory(prefix="medsurvey-mysql-fixture-") as temp:
            directory = Path(temp)
            env = {k: v for k, v in os.environ.items() if not k.startswith(("APP_", "DB_", "MYSQL", "LICENSE", "RESTIC"))}
            env.update(APP_ENV="testing", APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=",
                       APP_CONFIG_CACHE=str(directory / "unused.php"), CACHE_STORE="array", MAIL_MAILER="array",
                       SESSION_DRIVER="array", QUEUE_CONNECTION="sync", BROADCAST_CONNECTION="null",
                       LICENSING_ENABLED="false", DB_CONNECTION="mysql", DB_HOST="127.0.0.1", DB_PORT=port,
                       DB_URL="", DB_SOCKET="", DB_USERNAME="fixture", DB_PASSWORD=auth["MYSQL_PASSWORD"],
                       DB_DATABASE="schema_cycle_test", DB_EXPECTED_MYSQL_VERSION=version,
                       FIXTURE_MYSQL_UUID=uuid, FIXTURE_TMP=temp,
                       MYSQLDUMP_PATH=make_wrapper(directory, "mysqldump", container),
                       MYSQL_PATH=make_wrapper(directory, "mysql", container),
                       FIXTURE_INCOMPATIBLE=make_wrapper(directory, "mysqldump", container, incompatible=True))
            verify_php("tests/mysql-schema-cycle.php", "PASS: production-shaped schema", env)
            try:
                verify_php("tests/mysql-backup-cycle.php", "PASS: native backup", env)
            except (subprocess.CalledProcessError, RuntimeError):
                for log in directory.glob("*.error"):
                    print(log.read_text(encoding="utf-8", errors="replace"), file=sys.stderr)
                raise
            verify_concurrent_submissions(admin, container, auth["MYSQL_PASSWORD"])
            if full_suite:
                env["DB_DATABASE"] = "parity_test"
                subprocess.run(["php", "vendor/phpunit/phpunit/phpunit"], env=env, cwd=ROOT, check=True)
    finally:
        docker("rm", "-f", "-v", container)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("image")
    parser.add_argument("--full-suite", action="store_true")
    args = parser.parse_args()
    run(args.image, args.full_suite)
