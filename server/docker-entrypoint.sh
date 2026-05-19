#!/bin/sh
set -e

if [ "${RUN_DB_MIGRATIONS:-true}" = "true" ]; then
  npx prisma migrate deploy
fi

exec "$@"
