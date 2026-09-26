<?php
$c = include '/home/pkjetp/jetpk_app/bootstrap/cache/config.php';
echo 'cached conversational=';
var_export($c['ota']['ai']['conversational_enabled'] ?? 'MISSING');
echo PHP_EOL;
echo 'cached planner=';
var_export($c['ota']['ai']['semantic_planner_enabled'] ?? 'MISSING');
echo PHP_EOL;
echo 'cached composer=';
var_export($c['ota']['ai']['semantic_composer_enabled'] ?? 'MISSING');
echo PHP_EOL;
echo 'cached brain=';
var_export($c['ota']['ai']['brain_enabled'] ?? 'MISSING');
echo PHP_EOL;
echo 'embed=';
var_export($c['ai_embed']['enabled'] ?? 'MISSING');
echo PHP_EOL;

require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'runtime conversational=' . (config('ota.ai.conversational_enabled') ? 'true' : 'false') . PHP_EOL;
echo 'runtime planner=' . (config('ota.ai.semantic_planner_enabled') ? 'true' : 'false') . PHP_EOL;
echo 'runtime composer=' . (config('ota.ai.semantic_composer_enabled') ? 'true' : 'false') . PHP_EOL;
echo 'runtime embed=' . (config('ai_embed.enabled') ? 'true' : 'false') . PHP_EOL;
$brain = config('ota.ai.brain_enabled');
if ($brain === null) {
    $brain = config('ai.brain_enabled');
}
echo 'runtime brain_enabled=' . json_encode($brain) . PHP_EOL;
echo 'runtime sha=' . trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha')) . PHP_EOL;
