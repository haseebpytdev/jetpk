#!/bin/bash
# Regression gate: SEO activate must not break AI runtime bindings.
set -euo pipefail

APP="${APP:-/home/pkjetp/jetpk_app}"
PHP="${PHP:-/usr/local/lsws/lsphp83/bin/php}"

fail() {
  echo "AI_RUNTIME_VERIFY=FAIL reason=$1"
  exit 1
}

if ! grep -q 'AiServiceProvider' "$APP/bootstrap/providers.php"; then
  fail "missing_AiServiceProvider_in_providers"
fi

if grep -q 'InferenceProvider' "$APP/app/Providers/AppServiceProvider.php" 2>/dev/null; then
  echo "WARN=InferenceProvider_still_in_AppServiceProvider"
fi

probe="$($PHP -r "
require '$APP/vendor/autoload.php';
\$app = require '$APP/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo get_class(\$app->make(App\Contracts\Ai\InferenceProvider::class)) . PHP_EOL;
echo get_class(\$app->make(App\Contracts\Ai\Lab\AiLabConsultantGateway::class)) . PHP_EOL;
echo get_class(\$app->make(App\Services\Ai\Lab\AiLabAdapter::class)) . PHP_EOL;
" 2>&1)" || fail "container_resolve_failed"

echo "$probe" | grep -qE 'LocalLlamaProvider|NullInferenceProvider|OpenAICompatibleProvider' || fail "inference_provider_not_resolved"
echo "$probe" | grep -q 'HttpAiLabConsultantGateway' || fail "gateway_not_resolved"
echo "$probe" | grep -q 'AiLabAdapter' || fail "adapter_not_resolved"

health_code="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8765/health || echo 000)"
if [ "$health_code" != "200" ]; then
  fail "gateway_health_$health_code"
fi

if grep -q 'ai.lab.canary.fault' "$APP/bootstrap/app.php" 2>/dev/null; then
  echo "MIDDLEWARE_ALIAS=present"
else
  echo "MIDDLEWARE_ALIAS=missing"
fi

echo "AI_RUNTIME_VERIFY=PASS"
echo "$probe"
