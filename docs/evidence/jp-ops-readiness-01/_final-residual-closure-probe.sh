#!/usr/bin/env bash
# JP-FINAL-OPS-RESIDUAL-CLOSURE — Phase 0/1/5/8 read-only probe (no secrets printed)
set -euo pipefail

APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
PHASE="${1:-all}"

cd "$APP"

phase0() {
  echo "=== PHASE0_BASELINE ==="
  echo "HOSTNAME=$(hostname)"
  echo "UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  for u in /up /login /lookup-booking; do
    code=$(curl -sS -o /dev/null -w '%{http_code}' -m 20 "https://jetpakistan.pk${u}" || echo ERR)
    echo "HTTP${u}=${code}"
  done
  read -r TOTAL_K USED_K AVAIL_K PCT < <(df -Pk / | awk 'NR==2{print $2,$3,$4,$5}' | tr -d '%')
  FREE_GB=$(awk -v a="$AVAIL_K" 'BEGIN{printf "%.2f", a/1024/1024}')
  echo "DISK_USED_PERCENT=$PCT"
  echo "DISK_FREE_GB=$FREE_GB"
  "$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "FAILED_JOBS_TOTAL=".(int)Illuminate\Support\Facades\DB::table("failed_jobs")->count()."\n";
echo "DEFAULT_QUEUE_PENDING=".(int)Illuminate\Support\Facades\DB::table("jobs")->where("queue","default")->count()."\n";
echo "NOTIFICATIONS_TRANSACTIONAL_PENDING=".(int)Illuminate\Support\Facades\DB::table("jobs")->where("queue","notifications-transactional")->count()."\n";
$ext = filter_var(env("AIRBLUE_EXTERNAL_CALLS_ENABLED", false), FILTER_VALIDATE_BOOLEAN);
echo "AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED=".($ext?"YES":"NO")."\n";
'
  echo "=== CRON_DISK_HEALTH ==="
  if crontab -l 2>/dev/null | grep -E 'disk-health|ops-health' >/tmp/_cron_disk_match.txt; then
    echo "DISK_HEALTH_CRON_PRESENT=YES"
    # sanitize: show command pattern only
    sed -E 's/@[^ ]+/@MASKED/g; s/[0-9a-zA-Z._%+-]+@[0-9a-zA-Z.-]+/EMAIL_MASKED/g' /tmp/_cron_disk_match.txt | head -20
  else
    echo "DISK_HEALTH_CRON_PRESENT=NO"
  fi
  rm -f /tmp/_cron_disk_match.txt
  echo "=== DISK_HEALTH_SCRIPT ==="
  SCRIPT=""
  for p in \
    "$APP/scripts/jetpk/disk-health-check.sh" \
    /home/pkjetp/bin/disk-health-check.sh \
    /home/pkjetp/scripts/jetpk/disk-health-check.sh
  do
    if [[ -f "$p" ]]; then SCRIPT="$p"; break; fi
  done
  if [[ -z "$SCRIPT" ]]; then
    SCRIPT=$(find /home/pkjetp -maxdepth 5 -name 'disk-health-check.sh' 2>/dev/null | head -1 || true)
  fi
  if [[ -n "${SCRIPT:-}" && -f "$SCRIPT" ]]; then
    echo "DISK_HEALTH_SCRIPT_PRESENT=YES"
    echo "DISK_HEALTH_SCRIPT_PATH=$SCRIPT"
    if grep -q 'jetpk:ops-health-alert' "$SCRIPT"; then
      echo "DISK_HEALTH_ALERT_WIRING_PRESENT=YES"
    else
      echo "DISK_HEALTH_ALERT_WIRING_PRESENT=NO"
    fi
  else
    echo "DISK_HEALTH_SCRIPT_PRESENT=NO"
    echo "DISK_HEALTH_ALERT_WIRING_PRESENT=NO"
  fi
}

phase1_ops_env() {
  echo "=== PHASE1_OPS_ENV ==="
  "$PHP" -r '
$keys=["OPS_EXTERNAL_ALERTS_ENABLED","OPS_ALERT_EMAIL","OPS_ALERT_DRY_RUN"];
$lines=@file(".env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
foreach ($keys as $k) {
  $hit=false;
  foreach ($lines as $line) {
    if (str_starts_with(ltrim($line), "#")) continue;
    if (preg_match("/^".preg_quote($k,"/")."=(.*)$/", $line, $m)) {
      $hit=true;
      $val=trim($m[1], "\"'\''");
      $nonempty = $val !== "";
      echo $k."_PRESENT=YES\n";
      echo $k."_NONEMPTY=".($nonempty?"YES":"NO")."\n";
      if ($k === "OPS_ALERT_EMAIL" && $nonempty) {
        echo "OPS_ALERT_EMAIL_HASH=".substr(hash("sha256", $val), 0, 12)."\n";
      }
      if (in_array($k, ["OPS_EXTERNAL_ALERTS_ENABLED","OPS_ALERT_DRY_RUN"], true)) {
        echo $k."_BOOL=".(filter_var($val, FILTER_VALIDATE_BOOLEAN) ? "true" : "false")."\n";
      }
      break;
    }
  }
  if (!$hit) echo $k."_PRESENT=NO\n";
}
'
  echo "=== RUNTIME_OPS_CONFIG ==="
  "$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dest=trim((string)config("ops.alert_email",""));
echo "OPS_ALERT_ROUTER_CLASS=".(class_exists("App\\Support\\Ops\\OpsExternalAlertRouter")?"PRESENT":"ABSENT")."\n";
echo "OPS_ALERT_TRIGGER_CLASS=".(class_exists("App\\Support\\Ops\\OpsHealthAlertTriggers")?"PRESENT":"ABSENT")."\n";
echo "OPS_EXTERNAL_ALERTS_ENABLED_RUNTIME=".(config("ops.external_alerts_enabled")?"YES":"NO")."\n";
echo "OPS_ALERT_EMAIL_CONFIGURED=".($dest!==""?"YES":"NO")."\n";
if ($dest!=="") echo "OPS_ALERT_EMAIL_HASH=".substr(hash("sha256",$dest),0,12)."\n";
echo "OPS_ALERT_DRY_RUN_RUNTIME=".(config("ops.alert_dry_run")?"YES":"NO")."\n";
'
}

phase5_root_owned() {
  echo "=== PHASE5_ROOT_OWNED_CANDIDATES ==="
  CANDIDATES=(
    "/home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z"
    "/home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z"
    "/home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z"
    "/home/pkjetp/releases/jetpk-20260930T080534Z"
  )
  CURRENT_LINK=""
  if [[ -L /home/pkjetp/jetpk_app ]]; then
    CURRENT_LINK=$(readlink -f /home/pkjetp/jetpk_app || true)
  fi
  echo "JETPK_APP_RESOLVED=$(readlink -f /home/pkjetp/jetpk_app 2>/dev/null || echo /home/pkjetp/jetpk_app)"
  echo "JETPK_APP_IS_SYMLINK=$([[ -L /home/pkjetp/jetpk_app ]] && echo YES || echo NO)"
  COUNT=0
  BYTES=0
  for p in "${CANDIDATES[@]}"; do
    echo "--- CANDIDATE=$p ---"
    if [[ ! -e "$p" ]]; then
      echo "EXISTS=NO"
      continue
    fi
    echo "EXISTS=YES"
    owner=$(stat -c '%U:%G' "$p" 2>/dev/null || echo UNKNOWN)
    mode=$(stat -c '%a' "$p" 2>/dev/null || echo UNKNOWN)
    mtime=$(stat -c '%y' "$p" 2>/dev/null | cut -d. -f1 || echo UNKNOWN)
    if [[ -d "$p" ]]; then
      size=$(du -sb "$p" 2>/dev/null | awk '{print $1}')
    else
      size=$(stat -c '%s' "$p" 2>/dev/null || echo 0)
    fi
    echo "OWNER=$owner"
    echo "MODE=$mode"
    echo "MTIME=$mtime"
    echo "SIZE_BYTES=$size"
    echo "SIZE_HUMAN=$(du -sh "$p" 2>/dev/null | awk '{print $1}')"
    resolved=$(readlink -f "$p" 2>/dev/null || echo "$p")
    is_runtime=NO
    if [[ "$resolved" == "$(readlink -f /home/pkjetp/jetpk_app)" ]]; then is_runtime=YES; fi
    if [[ -n "$CURRENT_LINK" && "$resolved" == "$CURRENT_LINK" ]]; then is_runtime=YES; fi
    echo "IS_CURRENT_RUNTIME=$is_runtime"
    # mount check
    if findmnt -T "$p" >/dev/null 2>&1; then
      mnt=$(findmnt -no TARGET -T "$p" 2>/dev/null || echo UNKNOWN)
      echo "MOUNT_POINT=$mnt"
      echo "IS_MOUNT_ROOT=$([[ "$mnt" == "$p" ]] && echo YES || echo NO)"
    fi
    # open files (best effort)
    open_count=$(lsof +D "$p" 2>/dev/null | wc -l || echo 0)
    echo "LSOF_LINES=$open_count"
    # cron/deploy refs (name only)
    base=$(basename "$p")
    cron_ref=$(crontab -l 2>/dev/null | grep -F "$base" | wc -l || echo 0)
    echo "CRON_NAME_REFS=$cron_ref"
    if [[ "$owner" == root:root || "$owner" == root:* ]]; then
      if [[ "$is_runtime" == NO ]]; then
        COUNT=$((COUNT+1))
        BYTES=$((BYTES+size))
        echo "SAFETY_CLASS_CANDIDATE=ROOT_OWNED_NON_RUNTIME"
      fi
    fi
  done
  # scan additional root-owned under backups/releases/tmp (names only, limited)
  echo "=== ADDITIONAL_ROOT_OWNED_SCAN ==="
  for dir in /home/pkjetp/backups /home/pkjetp/releases /tmp; do
    [[ -d "$dir" ]] || continue
    while IFS= read -r line; do
      echo "EXTRA_ROOT_OWNED=$line"
    done < <(find "$dir" -maxdepth 2 \( -user root -o -group root \) -printf '%p|%u:%g|%s|%TY-%Tm-%Td\n' 2>/dev/null | head -40)
  done
  echo "ROOT_OWNED_OBSOLETE_COUNT_CANDIDATES=$COUNT"
  echo "ROOT_OWNED_OBSOLETE_BYTES_CANDIDATES=$BYTES"
}

phase4_mailbox() {
  echo "=== PHASE4_MAILBOX_FORENSICS ==="
  "$PHP" <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$prefix = "9755902a7672";
$cols = ["id","email","current_agency_id","account_type","status","updated_at"];
$users = Illuminate\Support\Facades\DB::table("users")->select($cols)->get();
$hits = [];
foreach ($users as $u) {
  $email = strtolower(trim((string)$u->email));
  if ($email === "") continue;
  $h = hash("sha256", $email);
  if (str_starts_with($h, $prefix)) {
    $hits[] = $u;
  }
}
echo "BAD_MAILBOX_HASH=".$prefix."\n";
echo "MATCHING_USER_COUNT=".count($hits)."\n";
if (count($hits) === 0) {
  echo "AUTHORITATIVE_REPLACEMENT_FOUND=NO\n";
  echo "AGENCY_ADMIN_MAILBOX_STATUS=NOT_FOUND\n";
  exit(0);
}
foreach ($hits as $u) {
  echo "--- USER_HIT ---\n";
  echo "USER_ID_HASH=".substr(hash("sha256",(string)$u->id),0,12)."\n";
  echo "AGENCY_ID_OR_HASH=".($u->current_agency_id !== null ? (string)$u->current_agency_id : "null")."\n";
  echo "ACCOUNT_TYPE=".($u->account_type ?? "unknown")."\n";
  echo "STATUS=".($u->status ?? "n/a")."\n";
  $agencyId = $u->current_agency_id;
  $candidates = [];

  // agency_users membership
  if (Illuminate\Support\Facades\Schema::hasTable("agency_users") && $agencyId) {
    $memberships = Illuminate\Support\Facades\DB::table("agency_users")->where("user_id", $u->id)->get();
    echo "AGENCY_USER_MEMBERSHIPS=".$memberships->count()."\n";
  }

  if ($agencyId) {
    $agency = Illuminate\Support\Facades\DB::table("agencies")->where("id", $agencyId)->first();
    if ($agency) {
      $agencyCols = array_keys((array)$agency);
      echo "AGENCY_COLUMNS=".implode(",", $agencyCols)."\n";
      foreach (["email","contact_email","billing_email","admin_email","primary_email"] as $field) {
        if (!property_exists($agency, $field) && !isset($agency->$field)) {
          echo "AGENCY_FIELD_{$field}_PRESENT=COLUMN_ABSENT\n";
          continue;
        }
        $val = strtolower(trim((string)($agency->$field ?? "")));
        if ($val !== "" && filter_var($val, FILTER_VALIDATE_EMAIL)) {
          $eh = hash("sha256", $val);
          $domain = substr(strrchr($val, "@") ?: "", 1);
          echo "AGENCY_FIELD_{$field}_PRESENT=YES\n";
          echo "AGENCY_FIELD_{$field}_HASH=".substr($eh,0,12)."\n";
          echo "AGENCY_FIELD_{$field}_DOMAIN=$domain\n";
          if (!str_starts_with($eh, $prefix)) {
            $candidates["agencies.$field"] = ["hash"=>substr($eh,0,12),"domain"=>$domain];
          }
        } else {
          echo "AGENCY_FIELD_{$field}_PRESENT=NO\n";
        }
      }
      // settings JSON contact keys only (authoritative if explicitly stored)
      $settings = $agency->settings ?? null;
      if (is_string($settings)) {
        $settings = json_decode($settings, true);
      }
      if (is_array($settings)) {
        foreach (["email","contact_email","billing_email","admin_email","primary_email","notification_email"] as $field) {
          if (!array_key_exists($field, $settings)) continue;
          $val = strtolower(trim((string)$settings[$field]));
          if ($val !== "" && filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $eh = hash("sha256", $val);
            $domain = substr(strrchr($val, "@") ?: "", 1);
            echo "AGENCY_SETTINGS_{$field}_PRESENT=YES\n";
            echo "AGENCY_SETTINGS_{$field}_HASH=".substr($eh,0,12)."\n";
            echo "AGENCY_SETTINGS_{$field}_DOMAIN=$domain\n";
            if (!str_starts_with($eh, $prefix)) {
              $candidates["agencies.settings.$field"] = ["hash"=>substr($eh,0,12),"domain"=>$domain];
            }
          }
        }
      }
    }

    // other same-agency users — informational only, NOT authoritative replacement for this identity
    $others = Illuminate\Support\Facades\DB::table("users")
      ->where("current_agency_id", $agencyId)
      ->where("id", "!=", $u->id)
      ->select("id","email","account_type")
      ->get();
    echo "SAME_AGENCY_OTHER_USERS=".$others->count()."\n";
    foreach ($others as $o) {
      $oe = strtolower(trim((string)$o->email));
      if ($oe === "" || !filter_var($oe, FILTER_VALIDATE_EMAIL)) continue;
      $oh = hash("sha256", $oe);
      echo "OTHER_USER_TYPE=".($o->account_type ?? "?")." HASH=".substr($oh,0,12)." DOMAIN=".substr(strrchr($oe,"@")?: "",1)."\n";
    }
  } else {
    echo "AGENCY_ID_OR_HASH=null\n";
  }

  $uniq = [];
  foreach ($candidates as $field => $info) {
    $uniq[$info["hash"]] = ["field"=>$field,"hash"=>$info["hash"],"domain"=>$info["domain"]];
  }
  if (count($uniq) === 1) {
    $only = array_values($uniq)[0];
    echo "AUTHORITATIVE_REPLACEMENT_FOUND=YES\n";
    echo "REPLACEMENT_SOURCE_FIELD=".$only["field"]."\n";
    echo "REPLACEMENT_EMAIL_HASH=".$only["hash"]."\n";
    echo "REPLACEMENT_DOMAIN_ONLY=".$only["domain"]."\n";
  } else {
    echo "AUTHORITATIVE_REPLACEMENT_FOUND=NO\n";
    echo "REPLACEMENT_CANDIDATE_COUNT=".count($uniq)."\n";
    echo "AGENCY_ADMIN_MAILBOX_STATUS=BLOCKED_NEEDS_OPERATOR_EMAIL\n";
  }
}
PHP
}

phase8_airblue() {
  echo "=== PHASE8_AIRBLUE_PREFLIGHT ==="
  "$PHP" <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

$rows = SupplierConnection::query()->where("provider", SupplierProvider::Airblue)->get();
echo "AIRBLUE_CONNECTION_PRESENT=".($rows->isNotEmpty()?"YES":"NO")."\n";
echo "AIRBLUE_CONNECTION_COUNT=".$rows->count()."\n";

foreach ($rows as $i => $c) {
  $cred = is_array($c->credentials) ? $c->credentials : [];
  $env = (string) ($c->environment?->value ?? $c->environment ?? "");
  $proto = (string) ($cred["protocol_version"] ?? $cred["protocol"] ?? "");
  $channel = (string) ($cred["api_channel"] ?? "");
  $base = trim((string)($c->base_url ?? ""));
  $hostOnly = "";
  if ($base !== "") {
    $hostOnly = parse_url($base, PHP_URL_HOST) ?: "";
  }
  // credential field presence (no values)
  $clientId = trim((string)($cred["client_id"] ?? $cred["ClientId"] ?? $cred["username"] ?? ""));
  $clientKey = trim((string)($cred["client_key"] ?? $cred["ClientKey"] ?? $cred["password"] ?? $cred["client_secret"] ?? ""));
  $agentType = trim((string)($cred["agent_type"] ?? $cred["AgentType"] ?? ""));
  $agentId = trim((string)($cred["agent_id"] ?? $cred["AgentId"] ?? ""));
  $agentPass = trim((string)($cred["agent_password"] ?? $cred["AgentPassword"] ?? ""));
  $tlsCert = trim((string)($cred["tls_cert_path"] ?? $cred["cert_path"] ?? $cred["ssl_cert"] ?? ""));
  $tlsKey = trim((string)($cred["tls_key_path"] ?? $cred["key_path"] ?? $cred["ssl_key"] ?? ""));
  $certStatus = (string)($cred["certification_status"] ?? "");
  $searchCertified = filter_var($cred["search_certified"] ?? false, FILTER_VALIDATE_BOOLEAN);
  $bookingEnabled = filter_var($cred["booking_enabled"] ?? true, FILTER_VALIDATE_BOOLEAN);
  $ticketEnabled = filter_var($cred["ticketing_enabled"] ?? false, FILTER_VALIDATE_BOOLEAN);
  $cancelEnabled = filter_var($cred["cancel_enabled"] ?? false, FILTER_VALIDATE_BOOLEAN);

  echo "--- connection_index=$i ---\n";
  echo "AIRBLUE_CONNECTION_ACTIVE=".($c->is_active?"YES":"NO")."\n";
  echo "AIRBLUE_ENVIRONMENT=".($env!==""?$env:"unset")."\n";
  echo "AIRBLUE_PROTOCOL_VERSION=".($proto!==""?$proto:"unset")."\n";
  echo "AIRBLUE_API_CHANNEL=".($channel!==""?$channel:"unset")."\n";
  echo "AIRBLUE_ENDPOINT_CONFIGURED=".($base!==""?"YES":"NO")."\n";
  echo "AIRBLUE_ENDPOINT_HOST_ONLY=".($hostOnly!==""?$hostOnly:"unset_uses_default")."\n";
  echo "AIRBLUE_CLIENT_ID_CONFIGURED=".($clientId!==""?"YES":"NO")."\n";
  echo "AIRBLUE_CLIENT_KEY_CONFIGURED=".($clientKey!==""?"YES":"NO")."\n";
  echo "AIRBLUE_AGENT_TYPE_CONFIGURED=".($agentType!==""?"YES":"NO")."\n";
  echo "AIRBLUE_AGENT_ID_CONFIGURED=".($agentId!==""?"YES":"NO")."\n";
  echo "AIRBLUE_AGENT_PASSWORD_CONFIGURED=".($agentPass!==""?"YES":"NO")."\n";
  echo "AIRBLUE_TLS_CERT_CONFIGURED=".($tlsCert!==""?"YES":"NO")."\n";
  echo "AIRBLUE_TLS_KEY_CONFIGURED=".($tlsKey!==""?"YES":"NO")."\n";
  if ($tlsCert!=="" && $tlsKey!=="") {
    echo "AIRBLUE_TLS_FILES_READABLE=".((@is_readable($tlsCert) && @is_readable($tlsKey))?"YES":"NO")."\n";
  } else {
    echo "AIRBLUE_TLS_FILES_READABLE=N/A\n";
  }
  echo "AIRBLUE_CERTIFICATION_STATUS=".($certStatus!==""?$certStatus:"unset")."\n";
  echo "AIRBLUE_SEARCH_CERTIFIED=".($searchCertified?"YES":"NO")."\n";
  echo "AIRBLUE_BOOKING_FEATURE_ENABLED=".($bookingEnabled?"YES":"NO")."\n";
  echo "AIRBLUE_TICKETING_FEATURE_ENABLED=".($ticketEnabled?"YES":"NO")."\n";
  echo "AIRBLUE_CANCEL_FEATURE_ENABLED=".($cancelEnabled?"YES":"NO")."\n";
}

$extEnvPresent = false;
$lines=@file(".env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
foreach ($lines as $line) {
  if (str_starts_with(ltrim($line), "#")) continue;
  if (preg_match('/^AIRBLUE_EXTERNAL_CALLS_ENABLED=/', $line)) { $extEnvPresent=true; break; }
}
echo "AIRBLUE_EXTERNAL_CALLS_ENV_PRESENT=".($extEnvPresent?"YES":"NO")."\n";
$ext = filter_var(env("AIRBLUE_EXTERNAL_CALLS_ENABLED", false), FILTER_VALIDATE_BOOLEAN);
echo "AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED=".($ext?"YES":"NO")."\n";
$cli = filter_var(env("AIRBLUE_CLI_LIVE_ENABLED", false), FILTER_VALIDATE_BOOLEAN);
echo "AIRBLUE_CLI_LIVE_ENABLED=".($cli?"YES":"NO")."\n";
$v3 = filter_var(config("suppliers.airblue.protocols.3.0.live_enabled", false), FILTER_VALIDATE_BOOLEAN);
echo "AIRBLUE_OTA_V3_LIVE_ENABLED=".($v3?"YES":"NO")."\n";

// Code readiness (class/command presence only — no network)
$checks = [
  "AIRBLUE_SEARCH_CODE_READY" => class_exists("App\\Console\\Commands\\AirblueTestSearchCommand") || class_exists("App\\Console\\Commands\\AirBlueTestSearchCommand"),
  "AIRBLUE_BOOKING_CODE_READY" => class_exists("App\\Console\\Commands\\AirblueTestBookingCommand") || class_exists("App\\Console\\Commands\\AirBlueTestBookingCommand"),
  "AIRBLUE_RETRIEVE_CODE_READY" => class_exists("App\\Services\\Suppliers\\AirBlue\\AirBlueRetrieveService") || class_exists("App\\Services\\Suppliers\\Airblue\\AirBlueRetrieveService"),
  "AIRBLUE_CANCEL_CODE_READY" => class_exists("App\\Console\\Commands\\AirblueCancelCommitCommand") || class_exists("App\\Console\\Commands\\AirBlueCancelCommitCommand"),
  "AIRBLUE_TICKETING_CODE_READY" => class_exists("App\\Services\\Suppliers\\AirBlue\\AirBlueTicketingService") || class_exists("App\\Services\\Suppliers\\Airblue\\AirBlueTicketingService"),
  "AIRBLUE_VOID_CODE_READY" => class_exists("App\\Services\\Suppliers\\AirBlue\\AirBlueVoidTicketService") || class_exists("App\\Services\\Suppliers\\Airblue\\AirBlueVoidTicketService"),
];
foreach ($checks as $k=>$ok) {
  // also try artisan list fallback via file existence
  echo "$k=".($ok?"YES":"PROBE_FILE")."\n";
}
echo "AIRBLUE_EXTERNAL_NETWORK_CALLS=0\n";
PHP
  # File-based code readiness fallback
  echo "=== AIRBLUE_CODE_FILES ==="
  for f in \
    app/Console/Commands/AirblueTestSearchCommand.php \
    app/Console/Commands/AirBlueTestSearchCommand.php \
    app/Console/Commands/AirblueTestBookingCommand.php \
    app/Console/Commands/AirBlueTestBookingCommand.php \
    app/Console/Commands/AirblueCancelCommitCommand.php \
    app/Console/Commands/AirBlueCancelCommitCommand.php \
    app/Services/Suppliers/AirBlue/AirBlueTicketingService.php \
    app/Services/Suppliers/Airblue/AirBlueTicketingService.php \
    app/Services/Suppliers/AirBlue/AirBlueVoidTicketService.php \
    app/Services/Suppliers/Airblue/AirBlueVoidTicketService.php \
    app/Services/Suppliers/AirBlue/AirBlueRetrieveService.php \
    app/Services/Suppliers/Airblue/AirBlueRetrieveService.php
  do
    if [[ -f "$APP/$f" ]]; then echo "PRESENT=$f"; fi
  done
}

case "$PHASE" in
  0) phase0 ;;
  1) phase1_ops_env ;;
  4) phase4_mailbox ;;
  5) phase5_root_owned ;;
  8) phase8_airblue ;;
  all)
    phase0
    phase1_ops_env
    phase4_mailbox
    phase5_root_owned
    phase8_airblue
    ;;
  *) echo "Unknown phase $PHASE"; exit 2 ;;
esac

echo "=== PROBE_COMPLETE ==="
