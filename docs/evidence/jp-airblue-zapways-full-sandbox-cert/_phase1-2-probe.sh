#!/usr/bin/env bash
# Phase 1-2: connection identity + mTLS recovery (no Zapways SOAP yet)
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
SECRETS_DIR=/home/pkjetp/jetpk_secrets/zapways
CERT_NAME=jetpakistan-zapways.crt.pem
KEY_NAME=jetpakistan-zapways.key.pem
CERT_CANON="$SECRETS_DIR/$CERT_NAME"
KEY_CANON="$SECRETS_DIR/$KEY_NAME"

cd "$APP"
echo "=== PHASE1_CONNECTION ==="
"$PHP" <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

$rows = SupplierConnection::query()->where("provider", SupplierProvider::Airblue)->get();
echo "AIRBLUE_CONNECTION_COUNT=".$rows->count()."\n";
if ($rows->count() !== 1) {
  echo "AIRBLUE_CONNECTION_IDENTITY=FAIL_COUNT\n";
  exit(0);
}
$c = $rows->first();
$cred = is_array($c->credentials) ? $c->credentials : [];
$env = (string)($c->environment?->value ?? $c->environment ?? "");
$proto = (string)($cred["protocol_version"] ?? "");
$channel = (string)($cred["api_channel"] ?? "");
$base = trim((string)($c->base_url ?? ""));
$host = $base !== "" ? (parse_url($base, PHP_URL_HOST) ?: "") : "";
// resolve default QA host if base empty
if ($host === "") {
  $host = "otatest4.zapways.com"; // expected default for sandbox 2.0 — confirm via config
  $cfgHost = parse_url((string)config("suppliers.airblue.protocols.2.0.default_qa_base_url", ""), PHP_URL_HOST) ?: "";
  if ($cfgHost !== "") $host = $cfgHost;
  echo "AIRBLUE_ENDPOINT_SOURCE=default_qa_config\n";
} else {
  echo "AIRBLUE_ENDPOINT_SOURCE=connection_base_url\n";
}
echo "AIRBLUE_CONNECTION_ID=".$c->id."\n";
echo "AIRBLUE_CONNECTION_ACTIVE=".($c->is_active?"YES":"NO")."\n";
echo "AIRBLUE_ENVIRONMENT=$env\n";
echo "AIRBLUE_PROTOCOL_VERSION=$proto\n";
echo "AIRBLUE_API_CHANNEL=$channel\n";
echo "AIRBLUE_ENDPOINT_HOST_ONLY=$host\n";
echo "AIRBLUE_BASE_URL_OVERRIDE=".($base!==""?"YES":"NO")."\n";
$certPath = trim((string)($cred["tls_cert_path"] ?? ""));
$keyPath = trim((string)($cred["tls_key_path"] ?? ""));
echo "TLS_CERT_PATH_CONFIGURED=".($certPath!==""?"YES":"NO")."\n";
echo "TLS_KEY_PATH_CONFIGURED=".($keyPath!==""?"YES":"NO")."\n";
if ($certPath!=="") echo "TLS_CERT_PATH_BASENAME=".basename($certPath)."\n";
if ($keyPath!=="") echo "TLS_KEY_PATH_BASENAME=".basename($keyPath)."\n";
if ($certPath!=="") echo "TLS_CERT_PATH_DIR_HASH=".substr(hash("sha256", dirname($certPath)),0,12)."\n";
if ($keyPath!=="") echo "TLS_KEY_PATH_DIR_HASH=".substr(hash("sha256", dirname($keyPath)),0,12)."\n";
echo "AIRBLUE_CERTIFICATION_STATUS=".((string)($cred["certification_status"] ?? "unset"))."\n";
echo "AIRBLUE_SEARCH_CERTIFIED=".(filter_var($cred["search_certified"] ?? false, FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "AIRBLUE_TICKETING_FEATURE_ENABLED=".(filter_var($cred["ticketing_enabled"] ?? false, FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "AIRBLUE_CANCEL_FEATURE_ENABLED=".(filter_var($cred["cancel_enabled"] ?? false, FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "AIRBLUE_BOOKING_FEATURE_ENABLED=".(filter_var($cred["booking_enabled"] ?? true, FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
$prodHost = (stripos($host, "ota4.zapways.com") !== false && stripos($host, "otatest") === false);
echo "PRODUCTION_AIRBLUE_CONNECTION_USED=".($prodHost || $env==="production" || $env==="live" ? "YES":"NO")."\n";
$ok = ($env==="sandbox" && $channel==="zapways_ota" && $proto==="2.0" && $host==="otatest4.zapways.com" && !$prodHost);
echo "PHASE1_IDENTITY_GATE=".($ok?"PASS":"FAIL")."\n";
PHP

echo "=== PHASE2_MTLS_SEARCH ==="
echo "TLS_CERT_CANON_EXISTS=$([[ -f "$CERT_CANON" ]] && echo YES || echo NO)"
echo "TLS_KEY_CANON_EXISTS=$([[ -f "$KEY_CANON" ]] && echo YES || echo NO)"

# basename-only search (exclude heavy trees)
mapfile -t CERT_HITS < <(find /home/pkjetp /root \
  \( -path '*/vendor/*' -o -path '*/node_modules/*' -o -path '*/public/*' -o -path '*/storage/app/public/*' \) -prune -o \
  -type f -name "$CERT_NAME" -print 2>/dev/null | head -20)
mapfile -t KEY_HITS < <(find /home/pkjetp /root \
  \( -path '*/vendor/*' -o -path '*/node_modules/*' -o -path '*/public/*' -o -path '*/storage/app/public/*' \) -prune -o \
  -type f -name "$KEY_NAME" -print 2>/dev/null | head -20)

echo "TLS_CERT_CANDIDATE_COUNT=${#CERT_HITS[@]}"
echo "TLS_KEY_CANDIDATE_COUNT=${#KEY_HITS[@]}"
for p in "${CERT_HITS[@]:-}"; do
  [[ -z "${p:-}" ]] && continue
  echo "TLS_CERT_CANDIDATE_PATH_HASH=$(printf '%s' "$p" | sha256sum | awk '{print substr($1,1,12)}')"
  echo "TLS_CERT_CANDIDATE_DIR_HASH=$(printf '%s' "$(dirname "$p")" | sha256sum | awk '{print substr($1,1,12)}')"
  echo "TLS_CERT_CANDIDATE_OWNER=$(stat -c '%U:%G' "$p")"
  echo "TLS_CERT_CANDIDATE_MODE=$(stat -c '%a' "$p")"
done
for p in "${KEY_HITS[@]:-}"; do
  [[ -z "${p:-}" ]] && continue
  echo "TLS_KEY_CANDIDATE_PATH_HASH=$(printf '%s' "$p" | sha256sum | awk '{print substr($1,1,12)}')"
  echo "TLS_KEY_CANDIDATE_DIR_HASH=$(printf '%s' "$(dirname "$p")" | sha256sum | awk '{print substr($1,1,12)}')"
  echo "TLS_KEY_CANDIDATE_OWNER=$(stat -c '%U:%G' "$p")"
  echo "TLS_KEY_CANDIDATE_MODE=$(stat -c '%a' "$p")"
done

# Also print actual paths only to root/tmp log for restore (not for evidence) — keep in script vars
CERT_SRC=""
KEY_SRC=""
if [[ -f "$CERT_CANON" ]]; then CERT_SRC="$CERT_CANON"; elif [[ ${#CERT_HITS[@]} -gt 0 ]]; then CERT_SRC="${CERT_HITS[0]}"; fi
if [[ -f "$KEY_CANON" ]]; then KEY_SRC="$KEY_CANON"; elif [[ ${#KEY_HITS[@]} -gt 0 ]]; then KEY_SRC="${KEY_HITS[0]}"; fi

echo "TLS_CERT_SRC_PRESENT=$([[ -n "$CERT_SRC" ]] && echo YES || echo NO)"
echo "TLS_KEY_SRC_PRESENT=$([[ -n "$KEY_SRC" ]] && echo YES || echo NO)"

if [[ -z "$CERT_SRC" || -z "$KEY_SRC" ]]; then
  echo "AIRBLUE_SANDBOX_CERT_DECISION=BLOCKED_MISSING_MTLS_MATERIAL"
  exit 0
fi

# Restore to canonical if needed
mkdir -p "$SECRETS_DIR"
if [[ "$CERT_SRC" != "$CERT_CANON" ]]; then
  cp -a "$CERT_SRC" "$CERT_CANON"
  echo "TLS_CERT_RESTORED=YES"
else
  echo "TLS_CERT_RESTORED=NO_ALREADY_CANON"
fi
if [[ "$KEY_SRC" != "$KEY_CANON" ]]; then
  cp -a "$KEY_SRC" "$KEY_CANON"
  echo "TLS_KEY_RESTORED=YES"
else
  echo "TLS_KEY_RESTORED=NO_ALREADY_CANON"
fi

# Permissions model: dir 750, cert 640, key 640, owner pkjetp, group that PHP can read
# Detect PHP/runtime user
RUNTIME_USER=$(ps -o user= -C lsphp 2>/dev/null | head -1 | tr -d ' ' || true)
if [[ -z "$RUNTIME_USER" ]]; then RUNTIME_USER=$(ps aux | awk '/lsphp|litespeed/ && !/awk/{print $1; exit}'); fi
echo "RUNTIME_USER_GUESS=${RUNTIME_USER:-unknown}"

chown pkjetp:pkjetp "$SECRETS_DIR" "$CERT_CANON" "$KEY_CANON" 2>/dev/null || true
chmod 750 "$SECRETS_DIR"
chmod 640 "$CERT_CANON"
chmod 640 "$KEY_CANON"

# If runtime is nobody/apache/www-data, ensure group read via common group
if id nobody &>/dev/null; then
  # add group-readable; try chgrp to a shared group if exists
  if getent group nobody >/dev/null; then
    chgrp nobody "$SECRETS_DIR" "$CERT_CANON" "$KEY_CANON" 2>/dev/null || true
  elif getent group pkjetp >/dev/null; then
    # keep pkjetp group; ensure ACL if setfacl available
    if command -v setfacl >/dev/null; then
      setfacl -m u:nobody:rX "$SECRETS_DIR" 2>/dev/null || true
      setfacl -m u:nobody:r "$CERT_CANON" "$KEY_CANON" 2>/dev/null || true
      echo "TLS_ACL_NOBODY=ATTEMPTED"
    fi
  fi
fi

echo "TLS_CERT_EXISTS=$([[ -f "$CERT_CANON" ]] && echo YES || echo NO)"
echo "TLS_KEY_EXISTS=$([[ -f "$KEY_CANON" ]] && echo YES || echo NO)"
echo "TLS_CERT_MODE=$(stat -c '%a' "$CERT_CANON")"
echo "TLS_KEY_MODE=$(stat -c '%a' "$KEY_CANON")"
echo "TLS_DIR_MODE=$(stat -c '%a' "$SECRETS_DIR")"
echo "TLS_CERT_OWNER=$(stat -c '%U:%G' "$CERT_CANON")"
echo "TLS_KEY_OWNER=$(stat -c '%U:%G' "$KEY_CANON")"

# Readable as pkjetp
echo "TLS_CERT_READABLE_PKJETP=$([[ -r "$CERT_CANON" ]] && echo YES || echo NO)"
echo "TLS_KEY_READABLE_PKJETP=$([[ -r "$KEY_CANON" ]] && echo YES || echo NO)"

# Readable as PHP runtime via sudo -u if possible
for u in nobody apache www-data lsadm "$RUNTIME_USER"; do
  [[ -z "$u" || "$u" == "unknown" ]] && continue
  if id "$u" &>/dev/null; then
    cr=$(sudo -n -u "$u" test -r "$CERT_CANON" && echo YES || echo NO)
    kr=$(sudo -n -u "$u" test -r "$KEY_CANON" && echo YES || echo NO)
    echo "TLS_CERT_READABLE_AS_${u}=$cr"
    echo "TLS_KEY_READABLE_AS_${u}=$kr"
  fi
done

# Also test via php as current user
"$PHP" -r '
$c="/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.crt.pem";
$k="/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.key.pem";
echo "TLS_CERT_READABLE_RUNTIME=".(is_readable($c)?"YES":"NO")."\n";
echo "TLS_KEY_READABLE_RUNTIME=".(is_readable($k)?"YES":"NO")."\n";
'

# openssl validation without dumping material
if openssl x509 -in "$CERT_CANON" -noout -modulus >/tmp/_ab_cert_mod 2>/dev/null; then
  echo "TLS_CERT_VALID_PEM=YES"
else
  echo "TLS_CERT_VALID_PEM=NO"
fi
if openssl rsa -in "$KEY_CANON" -noout -modulus >/tmp/_ab_key_mod 2>/dev/null \
   || openssl ec -in "$KEY_CANON" -noout -modulus >/tmp/_ab_key_mod 2>/dev/null; then
  echo "TLS_KEY_VALID_PEM=YES"
else
  # try generic pkey
  if openssl pkey -in "$KEY_CANON" -noout -modulus >/tmp/_ab_key_mod 2>/dev/null; then
    echo "TLS_KEY_VALID_PEM=YES"
  else
    echo "TLS_KEY_VALID_PEM=NO"
  fi
fi
if [[ -f /tmp/_ab_cert_mod && -f /tmp/_ab_key_mod ]]; then
  if cmp -s /tmp/_ab_cert_mod /tmp/_ab_key_mod; then
    echo "TLS_CERT_KEY_MATCH=YES"
  else
    echo "TLS_CERT_KEY_MATCH=NO"
  fi
else
  echo "TLS_CERT_KEY_MATCH=UNKNOWN"
fi
rm -f /tmp/_ab_cert_mod /tmp/_ab_key_mod

# Ensure connection credentials point to canonical paths (update only if wrong basename/path)
"$PHP" <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
$c = SupplierConnection::query()->where("provider", SupplierProvider::Airblue)->first();
$cred = is_array($c->credentials) ? $c->credentials : [];
$wantCert = "/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.crt.pem";
$wantKey = "/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.key.pem";
$changed = false;
if (($cred["tls_cert_path"] ?? "") !== $wantCert) { $cred["tls_cert_path"] = $wantCert; $changed = true; }
if (($cred["tls_key_path"] ?? "") !== $wantKey) { $cred["tls_key_path"] = $wantKey; $changed = true; }
if ($changed) {
  $c->credentials = $cred;
  $c->save();
  echo "TLS_PATHS_UPDATED_ON_CONNECTION=YES\n";
} else {
  echo "TLS_PATHS_UPDATED_ON_CONNECTION=NO\n";
}
echo "TLS_PATHS_POINT_CANON=YES\n";
PHP

echo "PHASE2_COMPLETE"
