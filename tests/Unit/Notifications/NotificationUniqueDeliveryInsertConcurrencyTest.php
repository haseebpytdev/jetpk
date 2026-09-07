<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;

class NotificationUniqueDeliveryInsertConcurrencyTest extends TestCase
{
    public function test_two_php_processes_same_delivery_tuple_yield_one_row(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jp-notif-del-'.bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $db = $dir.DIRECTORY_SEPARATOR.'race.sqlite';
        $readyA = $dir.DIRECTORY_SEPARATOR.'ready-a';
        $readyB = $dir.DIRECTORY_SEPARATOR.'ready-b';
        $go = $dir.DIRECTORY_SEPARATOR.'go';
        $table = 'notification_deliveries_race';

        $pdo = new \PDO('sqlite:'.$db);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('CREATE TABLE '.$table.' (
            event_id TEXT NOT NULL,
            channel TEXT NOT NULL,
            audience TEXT NOT NULL,
            recipient TEXT NOT NULL,
            idempotency_key TEXT PRIMARY KEY
        )');

        $actor = base_path('tests/bin/notification-unique-delivery-actor.php');
        $eventId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $php = PHP_BINARY;

        $spec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cmdA = [$php, $actor, $db, $eventId, $table, $readyA, $go];
        $cmdB = [$php, $actor, $db, $eventId, $table, $readyB, $go];
        $procA = proc_open($cmdA, $spec, $pipesA, null, null, ['bypass_shell' => true]);
        $procB = proc_open($cmdB, $spec, $pipesB, null, null, ['bypass_shell' => true]);
        $this->assertIsResource($procA);
        $this->assertIsResource($procB);

        $wait = microtime(true) + 8;
        while ((! is_file($readyA) || ! is_file($readyB)) && microtime(true) < $wait) {
            usleep(5000);
        }
        $this->assertFileExists($readyA);
        $this->assertFileExists($readyB);
        file_put_contents($go, '1');

        $outA = stream_get_contents($pipesA[1]);
        $outB = stream_get_contents($pipesB[1]);
        fclose($pipesA[1]);
        fclose($pipesA[2]);
        fclose($pipesB[1]);
        fclose($pipesB[2]);
        $codeA = proc_close($procA);
        $codeB = proc_close($procB);

        $this->assertSame(0, $codeA, $outA);
        $this->assertSame(0, $codeB, $outB);
        $decodedA = json_decode(trim($outA), true);
        $decodedB = json_decode(trim($outB), true);
        $this->assertIsArray($decodedA);
        $this->assertIsArray($decodedB);
        $this->assertSame(0, (int) $decodedA['uncaught'] + (int) $decodedB['uncaught']);
        $this->assertSame(1, (int) $decodedA['ok'] + (int) $decodedB['ok']);
        $this->assertSame($decodedA['key'], $decodedB['key']);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        $keys = (int) $pdo->query('SELECT COUNT(DISTINCT idempotency_key) FROM '.$table)->fetchColumn();
        $this->assertSame(1, $count);
        $this->assertSame(1, $keys);
    }
}
