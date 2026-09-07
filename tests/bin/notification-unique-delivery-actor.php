<?php

/**
 * Concurrent unique delivery insert. Args: sqlitePath eventId table ready go
 */
$db = $argv[1] ?? '';
$eventId = $argv[2] ?? '';
$table = $argv[3] ?? 'notification_deliveries_race';
$ready = $argv[4] ?? '';
$go = $argv[5] ?? '';

if ($db === '' || $eventId === '' || $ready === '' || $go === '') {
    fwrite(STDERR, "usage\n");
    exit(2);
}

$channel = 'email';
$audience = 'admin';
$recipient = 'owner@example.test';
$key = hash('sha256', implode('|', [
    $eventId,
    strtolower($channel),
    strtolower($audience),
    strtolower(trim($recipient)),
    'default',
]));

file_put_contents($ready, '1');
$deadline = microtime(true) + 8;
while (! is_file($go) && microtime(true) < $deadline) {
    usleep(5000);
}

$pdo = new PDO('sqlite:'.$db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 10,
]);
$pdo->exec('PRAGMA journal_mode=WAL');
$pdo->exec('PRAGMA busy_timeout=5000');

$uncaught = 0;
$ok = 0;
try {
    $stmt = $pdo->prepare(
        'INSERT INTO '.$table.' (event_id, channel, audience, recipient, idempotency_key) VALUES (:e,:c,:a,:r,:k)'
    );
    $stmt->execute([
        'e' => $eventId,
        'c' => $channel,
        'a' => $audience,
        'r' => $recipient,
        'k' => $key,
    ]);
    $ok = 1;
} catch (PDOException $e) {
    if (! str_contains($e->getMessage(), 'UNIQUE') && ! str_contains($e->getMessage(), 'unique')) {
        $uncaught = 1;
        fwrite(STDERR, $e->getMessage()."\n");
    }
}

echo json_encode(['ok' => $ok, 'uncaught' => $uncaught, 'key' => $key])."\n";
