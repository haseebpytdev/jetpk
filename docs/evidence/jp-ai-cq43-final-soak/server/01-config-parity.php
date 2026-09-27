<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$keys = [
  'ota.ai_assistant.conversational_enabled',
  'ota.ai_assistant.semantic_planner_enabled',
  'ota.ai_assistant.semantic_composer_enabled',
  'ai_embed.enabled',
  'ota.ai_assistant.enabled',
  'ota.ai_assistant.mode',
];
foreach ($keys as $k) {
  echo $k.'='.json_encode(config($k))."\n";
}
$s = app(App\Services\Ai\AiAssistantSettingsService::class)->effective();
echo 'effective.runtime_on='.json_encode($s['runtime_on'] ?? null)."\n";
echo 'effective.audience_mode='.json_encode($s['audience_mode'] ?? null)."\n";
$b = app(App\Services\Ai\Semantic\SemanticBrain::class);
echo 'brain_enabled='.json_encode($b->isEnabled())."\n";
echo 'RUNTIME_SHA='.trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'))."\n";
