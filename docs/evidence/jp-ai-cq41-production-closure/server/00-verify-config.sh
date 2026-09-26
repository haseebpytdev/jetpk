#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
echo "RUNTIME=$(tr -d '\n' < "$APP/.jetpk-runtime-sha")"
echo "AUTH=$(tr -d '\n' < "$APP/.jetpk-authorized-sha")"
echo "MARKER=$(tr -d '\n' < "$APP/storage/app/deploy-sha.txt")"
echo "FE=$(tr -d '\n' < "$APP/frontend/.jetpk-frontend-sha")"
echo "---ENV---"
grep -E '^(OTA_AI_CONVERSATIONAL_ENABLED|OTA_AI_SEMANTIC_PLANNER_ENABLED|OTA_AI_SEMANTIC_COMPOSER_ENABLED|AI_EMBED_ENABLED|OTA_AI_BRAIN|QWEN|SEMANTIC)' "$APP/.env" \
  | sed -E 's/(KEY|SECRET|TOKEN|PASSWORD|PASS)=.*/\1=***/i' || true
echo "---CONFIG---"
cd "$APP"
/usr/local/lsws/lsphp83/bin/php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "CONVERSATIONAL_ENABLED=" . (config("ota.ai.conversational_enabled") ? "true" : "false") . PHP_EOL;
echo "SEMANTIC_PLANNER_ENABLED=" . (config("ota.ai.semantic_planner_enabled") ? "true" : "false") . PHP_EOL;
echo "SEMANTIC_COMPOSER_ENABLED=" . (config("ota.ai.semantic_composer_enabled") ? "true" : "false") . PHP_EOL;
echo "AI_EMBED_ENABLED=" . (config("ai_embed.enabled") ? "true" : "false") . PHP_EOL;
$brain = config("ota.ai.brain_enabled");
if ($brain === null) { $brain = config("ai.brain_enabled"); }
echo "brain_enabled=" . (($brain === true || $brain === 1 || $brain === "1" || $brain === "true") ? "true" : json_encode($brain)) . PHP_EOL;
'
echo "---THINKING_BUNDLE---"
grep -l "Ask JetPakistan is thinking" "$APP"/frontend/.next/static/chunks/*.js 2>/dev/null | head -5 || echo "NONE"
pm2 jlist 2>/dev/null | /usr/local/lsws/lsphp83/bin/php -r '$j=json_decode(stream_get_contents(STDIN),true); foreach($j as $p){ if(str_contains($p["name"]??"","frontend")) echo ($p["name"]." status=".($p["pm2_env"]["status"]??"?")." pid=".($p["pid"]??"?").PHP_EOL); }' || pm2 list | head -8
