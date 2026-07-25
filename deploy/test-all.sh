#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=${APP_DIR:-/var/www/erm-pushsale}
BASE_URL=${BASE_URL:-${DOMAIN:+https://${DOMAIN}}}

cd "$APP_DIR"

php artisan optimize:clear

ARGS=(erm:test-all --seed --phpunit --audit --landing-flow --flow --json)

if [[ "${FRESH:-0}" == "1" ]]; then
  ARGS+=(--fresh)
fi

if [[ "${BUILD:-0}" == "1" ]]; then
  ARGS+=(--build)
fi

if [[ "${PAGES:-0}" == "1" ]]; then
  ARGS+=(--pages --all-pages)
fi

if [[ "${ROUTE_SMOKE:-1}" == "1" ]]; then
  ARGS+=(--route-smoke)
fi

if [[ "${ROUTE_QUERY_NOISE:-1}" == "0" ]]; then
  ARGS+=(--no-route-query-noise)
fi

if [[ -n "${BASE_URL}" ]]; then
  ARGS+=(--base-url="${BASE_URL}")
fi

if [[ -n "${PHONE:-}" ]]; then
  ARGS+=(--phone="${PHONE}")
fi

php artisan "${ARGS[@]}"
