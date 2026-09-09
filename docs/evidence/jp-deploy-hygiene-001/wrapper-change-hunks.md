# Wrapper change hunks — DEPLOY-HYGIENE-001

Inspect full post-fix wrappers under `wrappers/` in this evidence directory.
Pre-fix server SHA256 values are recorded in `wrapper-inventory.json`.

## jetpk-deploy.sh (`5477c989…` → `b6693c961…`)

### New runtime ownership variables

```bash
RUNTIME_USER="${RUNTIME_USER:-pkjetp}"
RUNTIME_GROUP="${RUNTIME_GROUP:-pkjetp}"
SCRIPT_HELPERS="${JETPK_SCRIPTS_ROOT:-/home/pkjetp/jetpk_app/scripts/jetpk}"
OWNERSHIP_HELPER="${SCRIPT_HELPERS}/assert-runtime-ownership.sh"
```

### Full-tree path: run composer/artisan as pkjetp

```diff
- $PHP "$(command -v composer)" install --no-dev --optimize-autoloader
+ sudo -u "$RUNTIME_USER" "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader

- npm ci / npm run build
+ sudo -u "$RUNTIME_USER" npm ci / npm run build

- $PHP artisan migrate:status
+ sudo -u "$RUNTIME_USER" "$PHP" artisan migrate:status
```

### End-of-deploy normalize + assert (all scopes)

```bash
echo "=== PHASE: runtime ownership normalize + assert ==="
APP_ROOT="$APP" RUNTIME_USER="$RUNTIME_USER" RUNTIME_GROUP="$RUNTIME_GROUP" MODE=normalize \
  bash "$OWNERSHIP_HELPER"
APP_ROOT="$APP" ... MODE=assert bash "$OWNERSHIP_HELPER"
# exits non-zero on RUNTIME_OWNERSHIP_ASSERT_FAIL / OWNERSHIP_HELPER_MISSING
```

## jetpk-pre-proxy-gate.sh (`717f17e2…` → `597e20f1…`)

### Artisan as pkjetp + hard ownership gate

```diff
- PENDING=$(/usr/local/lsws/lsphp83/bin/php /home/pkjetp/jetpk_app/artisan migrate:status ...)
+ PENDING=$(sudo -u "$RUNTIME_USER" "$PHP" "$APP/artisan" migrate:status ...)

+ echo "=== PHASE: runtime ownership assert gate ==="
+ APP_ROOT="$APP" MODE=assert bash "$OWNERSHIP_HELPER" || exit 1
```

`PRE_PROXY_GATE_PASS` is emitted only after `RUNTIME_OWNERSHIP_GATE=PASS`.

## jetpk-stage-release.sh (local `f7d4ea30…` → `766683ed…`)

### Post-extract ownership on release staging dir only

```diff
- ... tar xzf ... && mv ... DELETE_RUNTIME_FILES ...
+ ... tar xzf ... && mv ... DELETE_RUNTIME_FILES ... && chown -R pkjetp:pkjetp '${REMOTE_RELEASE_ROOT}' ...
```

## Legacy server copy reconciled

`/home/pkjetp/jetpk-stage-release.sh` updated to SHA `766683ed…` (same as `/tmp/jetpk-stage-release.sh`) during hygiene closure so stale generation `c94ebf1b…` cannot bypass fixed extract ownership.
