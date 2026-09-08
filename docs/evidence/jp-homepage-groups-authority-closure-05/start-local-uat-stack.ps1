$ErrorActionPreference = "Stop"
$repo = "C:\Users\khadi\ota-jetpk"
Set-Location $repo
$env:APP_URL = "http://127.0.0.1:8000"
$env:SESSION_DOMAIN = "127.0.0.1"
$env:OTA_CLIENT_REQUIRE_LOGIN_OTP = "false"
$env:OTP_DEMO_FIXED_ENABLED = "true"
$env:OTP_DEMO_FIXED_CODE = "123456"
$env:OTP_DEMO_ALLOWED_EMAILS = "jp-dash-03-qa-admin@jetpakistan.pk"
Start-Process -FilePath "php" -ArgumentList "artisan","serve","--host=127.0.0.1","--port=8000" -WorkingDirectory $repo -WindowStyle Hidden
Start-Sleep -Seconds 3
$env:PLAYWRIGHT_PORT = "3010"
Start-Process -FilePath "npm" -ArgumentList "run","start:smoke" -WorkingDirectory "$repo\frontend" -WindowStyle Hidden
$env:NEXT_PUBLIC_LARAVEL_API_BASE = "http://127.0.0.1:8000"
Start-Process -FilePath "npx" -ArgumentList "next","start","-H","127.0.0.1","-p","3001" -WorkingDirectory "$repo\dashboard" -WindowStyle Hidden
Write-Output "LOCAL_UAT_STACK_STARTED"
