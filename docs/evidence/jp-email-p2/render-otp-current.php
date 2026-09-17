<?php

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Support\Branding\ClientMailBrandingResolver;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config([
    'app.url' => 'http://localhost',
    'client.canonical_client.domain' => 'jetpakistan.pk',
    'ota_client.slug' => 'jetpk',
    'mail.from.address' => 'ota@jetpakistan.pk',
    'mail.from.name' => 'JetPakistan',
    'jetpk_email.brand' => [],
]);

$user = new User([
    'name' => 'QA Owner',
    'email' => 'qa.owner@example.test',
]);
$user->id = 2;

$branding = ClientMailBrandingResolver::resolve('jetpk');
$html = (new LoginOtpMail(
    user: $user,
    brandName: $branding->companyName,
    otpCode: '123456',
    expiryMinutes: 10,
    clientSlug: 'jetpk',
))->render();

$html = preg_replace('/\b\d{6}\b/', '******', $html) ?? $html;
$dir = __DIR__.'/fixtures';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$out = $dir.'/login-otp-current.html';
file_put_contents($out, $html);
echo 'WROTE='.$out.PHP_EOL;
echo 'HIT_LOCALHOST='.(stripos($html, 'localhost') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'HIT_NOWRAP='.(stripos($html, 'white-space:nowrap') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'HIT_STACKED_WIDTH='.(stripos($html, 'width="100%"') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'HIT_REQUEST_CONTEXT='.(stripos($html, 'Request context') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'HIT_WEB_SIGNIN='.(stripos($html, 'Web sign-in') !== false ? 'YES' : 'NO').PHP_EOL;
