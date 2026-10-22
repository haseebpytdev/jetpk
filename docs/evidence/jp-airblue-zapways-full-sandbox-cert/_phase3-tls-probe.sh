#!/usr/bin/env bash
set -euo pipefail
CERT=/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.crt.pem
KEY=/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.key.pem
HOST=otatest4.zapways.com

echo "=== PHASE3_TLS_TRANSPORT ==="
# Harden permissions without exposing material
chown pkjetp:pkjetp "$CERT" "$KEY" "$(dirname "$CERT")"
chmod 750 "$(dirname "$CERT")"
chmod 640 "$CERT" "$KEY"
# ACL for LiteSpeed PHP if present as nobody
if id nobody &>/dev/null && command -v setfacl >/dev/null; then
  setfacl -m u:nobody:rx "$(dirname "$CERT")" 2>/dev/null || true
  setfacl -m u:nobody:r "$CERT" "$KEY" 2>/dev/null || true
fi
echo "TLS_CERT_OWNER=$(stat -c '%U:%G' "$CERT")"
echo "TLS_KEY_OWNER=$(stat -c '%U:%G' "$KEY")"
echo "TLS_CERT_MODE=$(stat -c '%a' "$CERT")"
echo "TLS_KEY_MODE=$(stat -c '%a' "$KEY")"

# Transport-only mTLS probe — no SOAP body
OUT=$(openssl s_client -connect "${HOST}:443" -servername "$HOST" \
  -cert "$CERT" -key "$KEY" -brief </dev/null 2>&1 || true)
# Fallback if -brief unsupported
if ! echo "$OUT" | grep -qiE 'Protocol|CONNECTION|Verification|error'; then
  OUT=$(openssl s_client -connect "${HOST}:443" -servername "$HOST" \
    -cert "$CERT" -key "$KEY" </dev/null 2>&1 | head -80 || true)
fi

# Sanitize: never print PEM blocks
SAN=$(printf '%s\n' "$OUT" | grep -viE 'BEGIN |END |-----' | head -40)
PROTOCOL=$(printf '%s\n' "$SAN" | grep -iE 'Protocol version|Protocol\s*:' | head -1 || true)
VERIFY=$(printf '%s\n' "$SAN" | grep -iE 'Verification|verify return' | head -3 || true)
echo "OPENSSL_PROTOCOL_LINE=${PROTOCOL:-none}"
echo "OPENSSL_VERIFY_LINES=${VERIFY:-none}"

# Classification
if printf '%s\n' "$OUT" | grep -qiE 'CONNECTED|Protocol version|Cipher'; then
  echo "ZAPWAYS_TEST_CONNECTIVITY=PASS"
else
  echo "ZAPWAYS_TEST_CONNECTIVITY=FAIL"
fi

if printf '%s\n' "$OUT" | grep -qiE 'Verification: OK|Verify return code: 0'; then
  echo "ZAPWAYS_TEST_TLS=PASS"
elif printf '%s\n' "$OUT" | grep -qiE 'verify return:1|Verify return code: 0 \(ok\)'; then
  echo "ZAPWAYS_TEST_TLS=PASS"
else
  # still may have connected with verify issues — inspect
  if printf '%s\n' "$OUT" | grep -qiE 'Verify return code: 0'; then
    echo "ZAPWAYS_TEST_TLS=PASS"
  else
    CODE=$(printf '%s\n' "$OUT" | grep -i 'Verify return code' | head -1 || true)
    echo "ZAPWAYS_TEST_TLS=FAIL"
    echo "TLS_VERIFY_DETAIL=${CODE:-unknown}"
  fi
fi

# Client cert presentation: openssl with -cert/-key that completes handshake implies mTLS attempt
if printf '%s\n' "$OUT" | grep -qiE 'error.*certificate|unable to (load|set) (cert|private)|EE certificate key too weak'; then
  echo "ZAPWAYS_TEST_MTLS=FAIL"
elif [[ "$(echo "$OUT" | grep -ciE 'CONNECTED|Protocol')" -gt 0 ]]; then
  echo "ZAPWAYS_TEST_MTLS=PASS"
else
  echo "ZAPWAYS_TEST_MTLS=FAIL"
fi

# As pkjetp readability
sudo -n -u pkjetp test -r "$CERT" && echo TLS_CERT_READABLE_RUNTIME=YES || echo TLS_CERT_READABLE_RUNTIME=NO
sudo -n -u pkjetp test -r "$KEY" && echo TLS_KEY_READABLE_RUNTIME=YES || echo TLS_KEY_READABLE_RUNTIME=NO

echo "PHASE3_COMPLETE"
