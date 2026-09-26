#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
cd "$APP"
echo "=== .env ==="
grep -E '^(OTA_AI_|AI_EMBED)' .env | sed -E 's/(KEY|SECRET|TOKEN|PASSWORD)=.*/\1=***/i'
echo "=== cached ai_assistant ==="
/usr/local/lsws/lsphp83/bin/php -r '
$c=include "/home/pkjetp/jetpk_app/bootstrap/cache/config.php";
echo json_encode($c["ota"]["ai_assistant"] ?? "MISSING", JSON_PRETTY_PRINT).PHP_EOL;
echo "embed=".json_encode($c["ai_embed"]["enabled"] ?? "MISSING").PHP_EOL;
'
echo "=== runtime ==="
/usr/local/lsws/lsphp83/bin/php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "CONVERSATIONAL_ENABLED=".(config("ota.ai_assistant.conversational_enabled")?"true":"false").PHP_EOL;
echo "SEMANTIC_PLANNER_ENABLED=".(config("ota.ai_assistant.semantic_planner_enabled")?"true":"false").PHP_EOL;
echo "SEMANTIC_COMPOSER_ENABLED=".(config("ota.ai_assistant.semantic_composer_enabled")?"true":"false").PHP_EOL;
echo "AI_EMBED_ENABLED=".(config("ai_embed.enabled")?"true":"false").PHP_EOL;
$p=app(\App\Services\Ai\Semantic\QwenSemanticPlanner::class);
echo "planner_isEnabled=".($p->isEnabled()?"true":"false").PHP_EOL;
$b=app(\App\Services\Ai\Semantic\SemanticBrain::class);
echo "brain_isEnabled=".($b->isEnabled()?"true":"false").PHP_EOL;
$c=app(\App\Services\Ai\AiConversationalAgent::class);
echo "conversational_class=".get_class($c).PHP_EOL;
'
