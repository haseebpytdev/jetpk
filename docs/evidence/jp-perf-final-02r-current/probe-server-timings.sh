#!/usr/bin/env bash
# Read-only server timing probes for JP-PERF-FINAL-02R-CURRENT
set -euo pipefail
APP=/home/pkjetp/jetpk_app
OUT=/tmp/jp-perf-02r-server-timings.json

probe() {
  local name="$1"
  local url="$2"
  local t0=$(date +%s%3N)
  local code=$(curl -sS -o /tmp/jp-probe-body.txt -w "%{http_code}" "$url" 2>/dev/null || echo 000)
  local t1=$(date +%s%3N)
  local ms=$((t1 - t0))
  echo "{\"name\":\"$name\",\"url\":\"$url\",\"http_code\":$code,\"wall_ms\":$ms}"
}

{
  echo '{'
  echo '"measured_at":"'$(date -u +%Y-%m-%dT%H:%M:%SZ)'",'
  echo '"production_sha":"'$(cat $APP/.jetpk-runtime-sha)'",'
  echo '"probes":['
  probe content_homepage "https://jetpakistan.pk/laravel/api/public/content/homepage" | sed 's/$/,/'
  probe groups_facets "https://jetpakistan.pk/laravel/api/public/groups/search/facets" | sed 's/$/,/'
  probe groups_search_data "https://jetpakistan.pk/groups/search/data?category=all" | sed 's/$/,/'
  probe group_package "https://jetpakistan.pk/groups/package/ALH-3335" | sed 's/$/,/'
  probe live_home "https://jetpakistan.pk/" | sed 's/$/,/'
  probe public_local "http://127.0.0.1:3010/"
  echo ']'
  echo '}'
} > "$OUT"
cat "$OUT"
