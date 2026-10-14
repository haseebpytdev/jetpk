<?php
declare(strict_types=1);
$envFile = '/home/pkjetp/jetpk_app/.env';
$c = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    if (str_starts_with($k, 'DB_')) {
        $c[$k] = trim($v, " \t\"'");
    }
}
$host = $c['DB_HOST'] ?? '127.0.0.1';
$user = $c['DB_USERNAME'] ?? '';
$pass = $c['DB_PASSWORD'] ?? '';
$port = (int) ($c['DB_PORT'] ?? 3306);
$mysqli = @new mysqli($host, $user, $pass, '', $port);
if ($mysqli->connect_errno) {
    echo "MYSQL_CONNECT_FAIL\n";
    exit(1);
}
$r = $mysqli->query('SELECT table_schema, ROUND(SUM(data_length+index_length)/1024/1024,2) AS mb FROM information_schema.tables GROUP BY table_schema ORDER BY mb DESC');
$jetpk = 0.0;
$other = 0.0;
while ($row = $r->fetch_assoc()) {
    $schema = $row['table_schema'];
    $mb = (float) $row['mb'];
    echo "SCHEMA_MB {$schema} {$mb}\n";
    if ($schema === 'jetpk_prod') {
        $jetpk = $mb;
    } elseif (! in_array($schema, ['information_schema', 'performance_schema', 'mysql', 'sys'], true)) {
        $other += $mb;
    }
}
echo 'JETPK_DATABASE_SIZE_MB='.$jetpk."\n";
echo 'OTHER_DATABASE_SIZE_MB='.$other."\n";
