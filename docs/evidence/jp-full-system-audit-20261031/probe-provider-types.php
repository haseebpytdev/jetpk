<?php

require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = App\Models\SupplierConnection::query()
    ->select(['id', 'provider', 'status', 'is_active', 'environment', 'agency_id', 'name'])
    ->orderBy('id')
    ->get();

foreach ($rows as $r) {
    $providerRaw = $r->getAttributes()['provider'] ?? null;
    $cast = $r->provider;
    $statusRaw = $r->getAttributes()['status'] ?? null;
    echo implode('|', [
        (string) $r->id,
        (string) $r->agency_id,
        (string) $providerRaw,
        is_object($cast) ? 'enum:'.get_class($cast) : 'type:'.gettype($cast),
        is_object($cast) ? $cast->value : (string) $cast,
        (string) $statusRaw,
        $r->is_active ? '1' : '0',
        (string) ($r->name ?? ''),
    ]) . PHP_EOL;
}
