#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
echo "=== ota.php ai section ==="
grep -n -A30 "'ai'" "$APP/config/ota.php" | head -60
echo "=== .env AI flags ==="
grep -E '^(OTA_AI_|AI_EMBED)' "$APP/.env" | sed -E 's/(KEY|SECRET|TOKEN|PASSWORD)=.*/\1=***/i'
echo "=== config cache keys under ota ==="
/usr/local/lsws/lsphp83/bin/php -r '
$c=include "/home/pkjetp/jetpk_app/bootstrap/cache/config.php";
echo "ota keys: ".implode(",", array_keys($c["ota"]??[])).PHP_EOL;
if(isset($c["ota"]["ai"])) { echo "ota.ai=".json_encode($c["ota"]["ai"]).PHP_EOL; }
else { echo "ota.ai MISSING\n"; }
'
echo "=== rebuild config ==="
cd "$APP"
/usr/local/lsws/lsphp83/bin/php artisan config:clear
/usr/local/lsws/lsphp83/bin/php artisan config:cache
echo "=== after rebuild ==="
/usr/local/lsws/lsphp83/bin/php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "CONVERSATIONAL_ENABLED=".(config("ota.ai.conversational_enabled")?"true":"false").PHP_EOL;
echo "SEMANTIC_PLANNER_ENABLED=".(config("ota.ai.semantic_planner_enabled")?"true":"false").PHP_EOL;
echo "SEMANTIC_COMPOSER_ENABLED=".(config("ota.ai.semantic_composer_enabled")?"true":"false").PHP_EOL;
echo "AI_EMBED_ENABLED=".(config("ai_embed.enabled")?"true":"false").PHP_EOL;
$p=app(\App\Services\Ai\Semantic\QwenSemanticPlanner::class);
echo "planner_isEnabled=".($p->isEnabled()?"true":"false").PHP_EOL;
$b=app(\App\Services\Ai\Semantic\SemanticBrain::class);
echo "brain_isEnabled=".($b->isEnabled()?"true":"false").PHP_EOL;
'
