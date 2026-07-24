<?php

namespace Tests\Ops;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ops')]
class OneApiOperationsScriptsTest extends TestCase
{
    public function test_v10_ops_scripts_exist_and_pass_bash_syntax_check(): void
    {
        if (PHP_OS_FAMILY === 'Windows' && trim((string) shell_exec('where bash 2>nul')) === '') {
            $this->markTestSkipped('bash not available');
        }

        $root = base_path();
        foreach ([
            'one-api-predeploy-backup-v10.sh',
            'one-api-rollback-v10.sh',
            'one-api-post-deploy-v10.sh',
        ] as $script) {
            $path = storage_path('app/'.$script);
            $this->assertFileExists($path);
            $output = [];
            $code = 0;
            exec('bash -n '.escapeshellarg($path).' 2>&1', $output, $code);
            $this->assertSame(0, $code, implode("\n", $output));
        }
    }
}
