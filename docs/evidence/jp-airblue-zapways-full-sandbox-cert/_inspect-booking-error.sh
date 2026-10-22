#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
for f in "$APP/storage/logs/air-blue-"*.log "$APP/storage/logs/air-blue.log" "$APP/storage/logs/laravel.log"; do
  [[ -f "$f" ]] || continue
  echo "=== FILE=$(basename "$f") ==="
  # last matching lines, redact obvious secrets
  grep -E 'airblue\.|supplier_http|air_book|DoOrderCreate|http_error|AirBlue|normalizedCode|status_code' "$f" \
    | tail -50 \
    | sed -E 's/(password|client_key|agent_password|Authorization|Bearer|ClientKey|AgentPassword)[=:"][^," ]+/\1=REDACTED/gi' \
    || true
done

# Recent diagnostic rows if table exists
cd "$APP"
/usr/local/lsws/lsphp83/bin/php <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "EXT=".(filter_var(env("AIRBLUE_EXTERNAL_CALLS_ENABLED", false), FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
if (Illuminate\Support\Facades\Schema::hasTable("supplier_diagnostics")) {
  $rows = Illuminate\Support\Facades\DB::table("supplier_diagnostics")->orderByDesc("id")->limit(5)->get(["id","provider","action","status","safe_message","created_at"]);
  foreach ($rows as $r) {
    echo "DIAG id={$r->id} action={$r->action} status={$r->status} msg=".substr((string)$r->safe_message,0,120)." at={$r->created_at}\n";
  }
} else {
  echo "DIAG_TABLE=absent\n";
}
// Check booking 41 meta error crumbs
$b = App\Models\Booking::find(41);
if ($b) {
  $meta = is_array($b->meta)?$b->meta:[];
  $ctx = is_array($meta["airblue_context"]??null)?$meta["airblue_context"]:[];
  echo "BOOKING41_OUTCOME=".($ctx["booking_outcome"]??"unset")."\n";
}
PHP
