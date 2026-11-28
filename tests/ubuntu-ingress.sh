#!/usr/bin/env bash
# Disposable CI host only. All listeners and Supervisor control sockets are private.
set -Eeuo pipefail
[[ ${APP_ENV:-} == testing && ${DB_DATABASE:-} == medsurvey_pro_test ]]
repo=$(pwd -P)
tmp=$(mktemp -d)
cleanup() {
  if [[ -s $tmp/supervisord.pid ]]; then supervisorctl -c "$tmp/supervisor.conf" shutdown >/dev/null || :; fi
  if [[ -s $tmp/nginx.pid ]]; then kill -QUIT "$(cat "$tmp/nginx.pid")" || :; fi
  if [[ -s $tmp/fpm.pid ]]; then kill -QUIT "$(cat "$tmp/fpm.pid")" || :; fi
  rm -rf -- "$tmp"
}
trap cleanup EXIT
export REVERB_APP_ID=ci REVERB_APP_KEY=fixture REVERB_APP_SECRET=fixture-secret
export REVERB_HOST=127.0.0.1 REVERB_PORT=18082 REVERB_SCHEME=http REVERB_ALLOWED_ORIGINS=allowed.test
export BROADCAST_CONNECTION=reverb QUEUE_CONNECTION=database DB_QUEUE=medsurvey-live DB_QUEUE_RETRY_AFTER=90
mkdir -p "$tmp/releases"
ln -s "$repo" "$tmp/current"
cat > "$tmp/fpm.conf" <<EOF
[global]
pid=$tmp/fpm.pid
error_log=$tmp/fpm.log
[medsurvey-test]
user=$(id -un)
group=$(id -gn)
listen=$tmp/fpm.sock
pm=static
pm.max_children=2
pm.status_path=/fpm-status
pm.status_listen=$tmp/status.sock
clear_env=no
EOF
php_fpm=${PHP_FPM_BIN:-/usr/sbin/php-fpm8.2}
php_cli=${PHP_CLI_BIN:-/usr/bin/php8.2}
"$php_fpm" -y "$tmp/fpm.conf" -D
sed -e "s@/var/www/medsurvey-releases@$tmp@g" -e "s@/run/php/php8.2-fpm.sock@$tmp/fpm.sock@g" \
    -e 's@127.0.0.1:8080@127.0.0.1:18082@g' deploy/ubuntu/nginx-public.inc.example > "$tmp/public.inc"
sed -e "s@/var/www/medsurvey-releases@$tmp@g" -e "s@/run/php/php8.2-fpm.sock@$tmp/fpm.sock@g" \
    -e "s@/run/php/medsurvey-status.sock@$tmp/status.sock@g" deploy/ubuntu/nginx-private.conf.example > "$tmp/private.conf"
sed -i 's@include fastcgi_params@include /etc/nginx/fastcgi_params@g' "$tmp/public.inc" "$tmp/private.conf"
cat > "$tmp/nginx.conf" <<EOF
pid $tmp/nginx.pid;
error_log $tmp/error.log;
events {}
http {
  access_log off;
  client_body_temp_path $tmp/body;
  proxy_temp_path $tmp/proxy;
  fastcgi_temp_path $tmp/fcgi;
  uwsgi_temp_path $tmp/uwsgi;
  scgi_temp_path $tmp/scgi;
  server { listen 127.0.0.1:18080; include $tmp/public.inc; }
  include $tmp/private.conf;
}
EOF
/usr/sbin/nginx -t -p "$tmp" -c "$tmp/nginx.conf"
/usr/sbin/nginx -p "$tmp" -c "$tmp/nginx.conf"
cat > "$tmp/supervisor.conf" <<EOF
[unix_http_server]
file=$tmp/supervisor.sock
[supervisord]
pidfile=$tmp/supervisord.pid
logfile=$tmp/supervisord.log
childlogdir=$tmp
[rpcinterface:supervisor]
supervisor.rpcinterface_factory=supervisor.rpcinterface:make_main_rpcinterface
[supervisorctl]
serverurl=unix://$tmp/supervisor.sock
EOF
sed -e "s@/var/www/medsurvey-releases@$tmp@g" -e "s@/usr/bin/php8.2@$php_cli@g" -e 's/--port=8080/--port=18082/' \
    -e "s/^user=www-data/user=$(id -un)/" deploy/ubuntu/supervisor.conf.example >> "$tmp/supervisor.conf"
supervisord -c "$tmp/supervisor.conf"
ctl() { supervisorctl -c "$tmp/supervisor.conf" "$@"; }
ctl start medsurvey-reverb
ctl start medsurvey-queue
"$php_cli" deploy/ubuntu/check-consumer.php "$repo" medsurvey-queue
"$php_cli" deploy/ubuntu/check-consumer.php "$repo" medsurvey-reverb
origin=http://127.0.0.1:18081/api/health
public=http://127.0.0.1:18080
curl -fsS "$origin" | php -r 'exit(json_decode(stream_get_contents(STDIN),true)["status"]==="ok"?0:1);'
curl -fsS "$public/api/health" >/dev/null
python3 tests/ws-probe.py allowed.test established
python3 tests/ws-probe.py refused.test refused
touch "$tmp/traffic.closed"
[[ $(curl -sS -w ' %{http_code}' "$public/api/health") == 'medsurvey-maintenance 503' ]]
[[ $(curl -sS -w ' %{http_code}' "$public/app/fixture") == 'medsurvey-maintenance 503' ]]
curl -fsS "$origin" >/dev/null
ctl stop medsurvey-queue
ctl stop medsurvey-reverb
ctl start medsurvey-reverb
ctl start medsurvey-queue
rm "$tmp/traffic.closed"
python3 tests/ws-probe.py allowed.test established
[[ $(curl -sS -o /dev/null -w '%{http_code}' "$public/.env") == 404 ]]
[[ $(curl -sS -o /dev/null -w '%{http_code}' "$public/apps/ci/events") == 404 ]]
echo 'PASS: Nginx/FPM private readiness, independent public gate, Supervisor stop/start, origin rejection and fresh WebSocket handshake'
