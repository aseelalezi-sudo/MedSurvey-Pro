"""Compare two locally built MedSurvey images on an isolated MySQL fixture (Docker)."""
import concurrent.futures as futures
import http.cookiejar
import json
import math
import os
import re
import secrets
import subprocess
import sys
import time
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PREFIX = "medsurvey-qa-" + secrets.token_hex(4)
PASSWORD = secrets.token_hex(20)
ENV = dict(os.environ, MYSQL_ROOT_PASSWORD=PASSWORD, DB_PASSWORD=PASSWORD,
           APP_KEY="base64:" + "A" * 43 + "=", TEST_ADMIN_PASSWORD=PASSWORD)
owned = []


def docker(*args, data=None):
    return subprocess.run(["docker", *args], input=data, capture_output=True, text=True,
                          env=ENV, check=True).stdout.strip()


def container(name, *args):
    name = PREFIX + "-" + name
    docker("run", "-d", "--name", name, "--network", PREFIX, *args)
    owned.append(name)
    return name


def sql(db, query):
    return docker("exec", "-i", db, "sh", "-c",
                  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -BN qa_e2e', data=query)


def serve(label, image, db):
    app = container(label, "--cpus=2", "--memory=2g", "-p", "127.0.0.1::80",
                    "-e", "APP_ENV=e2e", "-e", "APP_KEY", "-e", "APP_DEBUG=false",
                    "-e", "DB_CONNECTION=mysql", "-e", "DB_HOST=" + db,
                    "-e", "DB_USERNAME=root", "-e", "DB_PASSWORD", "-e", "DB_DATABASE=qa_e2e",
                    "-e", "CACHE_STORE=database", "-e", "SESSION_DRIVER=database",
                    "-e", "SESSION_SECURE_COOKIE=false", "-e", "TEST_ADMIN_PASSWORD", image)
    url = "http://" + docker("port", app, "80/tcp")
    return app, url


def api(url, path, payload=None, token=None):
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token:
        headers["Authorization"] = "Bearer " + token
    body = None if payload is None else json.dumps(payload).encode()
    with urllib.request.urlopen(urllib.request.Request(url + "/api/v1/" + path, body, headers), timeout=15) as response:
        return json.load(response)


def session(url):
    for label in ["A", "B"]:
        token = api(url, "auth/login", {"username": "browser-admin-" + label, "password": PASSWORD})["token"]
        assert api(url, "auth/me", token=token)["user"]["username"] == "browser-admin-" + label
        payload = json.dumps(api(url, "bootstrap", token=token))
        assert "Browser survey " + label in payload and "Browser survey " + ("B" if label == "A" else "A") not in payload
        api(url, "auth/logout", {}, token)
        try:
            api(url, "auth/me", token=token)
        except urllib.error.HTTPError as exc:
            assert exc.code == 401
        else:
            raise ValueError("Logout did not revoke the fixture token")
    assert {"build", "minBuild", "version"} <= api(url, "app/version").keys()
    jar = http.cookiejar.CookieJar()
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    with client.open(url + "/login") as response:
        csrf = re.search(r'name="_token"[^>]+value="([^"]+)"', response.read().decode())[1]
    data = urllib.parse.urlencode(dict(_token=csrf, username="browser-admin-A", password=PASSWORD)).encode()
    with client.open(url + "/login", data) as response:
        assert urllib.parse.urlparse(response.url).path == "/dashboard"
    return "; ".join(c.name + "=" + c.value for c in jar)


def resources(name):
    raw = docker("exec", name, "sh", "-c", "cat /sys/fs/cgroup/cpu.stat /sys/fs/cgroup/memory.current").splitlines()
    cpu = dict(line.split() for line in raw[:-1])
    return int(cpu["usage_usec"]), int(cpu["nr_throttled"]), int(raw[-1])


def measure(app, db, url, cookie, workers, count=None, duration=None):
    paths = ["/", "/survey-selection", "/dashboard", "/dashboard/responses", "/dashboard/reports"]
    def hit(i):
        path = paths[i % len(paths)]
        start = time.perf_counter()
        try:
            req = urllib.request.Request(url + path, headers={"Cookie": cookie} if path.startswith("/dashboard") else {})
            with urllib.request.urlopen(req, timeout=15) as response:
                response.read()
                ok = response.status == 200 and urllib.parse.urlparse(response.url).path == path
        except OSError:
            ok = False
        return 1000 * (time.perf_counter() - start), ok
    before = [resources(app), resources(db)]
    started = time.perf_counter()
    def run(worker):
        out = []
        i = worker
        while (i < count if count is not None else time.perf_counter() - started < duration):
            out.append(hit(i))
            i += workers
        return out
    with futures.ThreadPoolExecutor(workers) as pool:
        values = [v for batch in pool.map(run, range(workers)) for v in batch]
    elapsed = time.perf_counter() - started
    after = [resources(app), resources(db)]
    ms = sorted(v[0] for v in values)
    row = dict(n=len(ms), rps=round(len(ms) / elapsed, 1), errors=sum(not v[1] for v in values))
    for p in [50, 95, 99]:
        row["p" + str(p)] = round(ms[math.ceil(len(ms) * p / 100) - 1])
    for name, a, b in zip(["app", "db"], before, after):
        row[name + "_cpu"] = round((b[0] - a[0]) / (elapsed * 1e4), 1)
        row[name + "_throttles"] = b[1] - a[1]
        row[name + "_end_mib"] = round(b[2] / 1048576, 1)
    return row


def main():
    base, candidate, output = sys.argv[1:]
    db = container("db", "--cpus=1", "--memory=1g", "-e", "MYSQL_ROOT_PASSWORD",
                   "-e", "MYSQL_DATABASE=qa_e2e", "mysql:8.0.46@sha256:7dcddc01f13bab2f15cde676d44d01f61fc9f99fe7785e86196dfc07d358ae2b")
    for _ in range(60):
        try:
            if sql(db, "select 1") == "1":
                break
        except subprocess.CalledProcessError:
            pass
        time.sleep(1)
    apps = {k: serve(k, img, db) for k, img in [("base", base), ("candidate", candidate)]}
    fixture = apps["candidate"][0]
    docker("exec", fixture, "php", "artisan", "migrate", "--force")
    seed = (ROOT / "tests-e2e/seed-tenants.php").read_text().replace("dirname(__DIR__)", "__DIR__")
    docker("exec", "-i", fixture, "sh", "-c", "cat > qa-seed.php && php qa-seed.php", data=seed)
    sql(db, """SET SESSION cte_max_recursion_depth=6000;
        INSERT INTO survey_responses (id,surveyId,answers,department,overallScore,submittedAt,tenantId)
        WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n<5999)
        SELECT concat(s.id,'-',n),s.id,'[]',concat('ward-',mod(n,4)),80,'2026-01-01',s.tenantId FROM surveys s CROSS JOIN seq;""")
    assert sql(db, "select count(*) from survey_responses") == "12000"
    cookies = {}
    for name, (app, url) in apps.items():
        docker("exec", app, "php", "artisan", "optimize")
        cookies[name] = session(url)
        assert measure(app, db, url, cookies[name], 2, 30)["errors"] == 0
    rows = []
    for trial in range(1, 4):
        for name in (["base", "candidate"] if trial % 2 else ["candidate", "base"]):
            app, url = apps[name]
            for phase, workers, count, duration in [("ramp1",1,50,None),("ramp4",4,100,None),("ramp8",8,120,None),
                                                    ("steady",8,None,30),("spike",24,240,None),("recovery",2,60,None)]:
                row = dict(trial=trial, version=name, phase=phase, **measure(app, db, url, cookies[name], workers, count, duration))
                rows.append(row)
                print(json.dumps(row), flush=True)
                Path(output).write_text(json.dumps(rows, indent=2))
    assert all(r["errors"] == 0 for r in rows)


if __name__ == "__main__":
    docker("network", "create", PREFIX)
    try:
        main()
    except subprocess.CalledProcessError as exc:
        print("Fixture command failed:", exc.stderr[-1200:], file=sys.stderr)
        raise
    finally:
        for name in reversed(owned):
            docker("rm", "-fv", name)
        docker("network", "rm", PREFIX)
