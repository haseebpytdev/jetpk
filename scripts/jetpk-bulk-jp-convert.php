<?php

$root = __DIR__.'/../resources/views/dashboard/admin';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$map = [
    'btn btn-sm btn-outline-secondary' => 'jp-btn jp-btn--sm jp-btn--ghost',
    'btn btn-sm btn-outline-primary' => 'jp-btn jp-btn--sm jp-btn--outline',
    'btn btn-sm btn-primary' => 'jp-btn jp-btn--sm jp-btn--primary',
    'btn btn-sm btn-danger' => 'jp-btn jp-btn--sm jp-btn--danger',
    'btn btn-outline-primary' => 'jp-btn jp-btn--outline',
    'btn btn-outline-secondary' => 'jp-btn jp-btn--ghost',
    'btn btn-outline-danger' => 'jp-btn jp-btn--danger',
    'btn btn-primary' => 'jp-btn jp-btn--primary',
    'btn btn-secondary' => 'jp-btn jp-btn--secondary',
    'btn btn-danger' => 'jp-btn jp-btn--danger',
    'form-control' => 'jp-control',
    'form-select' => 'jp-control',
    'form-label' => 'jp-label',
    'class="card mb-3"' => 'class="jp-card"',
    'class="card mb-4"' => 'class="jp-card"',
    'class="card"' => 'class="jp-card"',
    'class="card-body"' => 'class="jp-card__body"',
    'class="card-header"' => 'class="jp-card__head"',
    'class="card-title' => 'class="jp-card__title',
    'row g-2 align-items-end' => 'jp-form-grid jp-form-grid--filter',
    'row g-2 align-items-center' => 'jp-between',
    'class="card-body p-0"' => 'class="jp-card__body jp-card__body--flush"',
    'class="card-body"' => 'class="jp-card__body"',
    'card-subtitle' => 'jp-card__subtitle',
    'alert alert-success' => 'jp-alert jp-alert--success',
    'alert alert-warning' => 'jp-alert jp-alert--warn',
    'alert alert-danger' => 'jp-alert jp-alert--danger',
    'alert alert-info' => 'jp-alert jp-alert--info',
    'table table-vcenter table-striped' => 'jp-table',
    'table table-vcenter card-table' => 'jp-table',
    'table table-vcenter' => 'jp-table',
    'table table-striped' => 'jp-table',
    'class="page-title"' => 'class="jp-page-title"',
];
$count = 0;
foreach ($rii as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }
    $path = $file->getPathname();
    $c = file_get_contents($path);
    $orig = $c;
    foreach ($map as $from => $to) {
        $c = str_replace($from, $to, $c);
    }
    if ($c !== $orig) {
        file_put_contents($path, $c);
        $count++;
    }
}
echo "updated {$count} files\n";
