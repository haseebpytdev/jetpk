<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Services\Suppliers\AlHaider\AlHaiderTokenRecord;
use App\Services\Suppliers\AlHaider\AlHaiderTokenStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class AlHaiderTokenStoreTest extends TestCase
{
    private AlHaiderTokenStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = app(AlHaiderTokenStore::class);
        $this->clearTokenArtifacts();
    }

    protected function tearDown(): void
    {
        $this->clearTokenArtifacts();
        parent::tearDown();
    }

    public function test_save_and_load_round_trip_without_exposing_plaintext_file(): void
    {
        Config::set('suppliers.al_haider.token_expiry_margin_seconds', 60);

        $record = $this->sampleRecord('secret-bearer-token');
        $this->store->save($record);
        $loaded = $this->store->load();

        $this->assertNotNull($loaded);
        $this->assertSame('secret-bearer-token', $loaded->token);
        $this->assertTrue($this->store->hasValidToken());

        $raw = File::get($this->store->absolutePath());
        $this->assertStringNotContainsString('secret-bearer-token', $raw);
    }

    public function test_first_write_creates_restricted_directory_and_file_modes_on_unix(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('POSIX permission assertions require Unix.');
        }

        $this->store->save($this->sampleRecord('first-write-token'));

        $directory = dirname($this->store->absolutePath());
        $this->assertDirectoryExists($directory);
        $this->assertSame($this->store->directoryMode(), fileperms($directory) & 0777);
        $this->assertSame($this->store->fileMode(), fileperms($this->store->absolutePath()) & 0777);
        $this->assertEmpty(glob($directory.'/auth-token.*.tmp') ?: []);
    }

    public function test_replacement_updates_token_and_remains_decryptable(): void
    {
        $this->store->save($this->sampleRecord('original-token'));
        $originalEncrypted = File::get($this->store->absolutePath());

        $this->store->save($this->sampleRecord('replacement-token'));

        $loaded = $this->store->load();
        $this->assertNotNull($loaded);
        $this->assertSame('replacement-token', $loaded->token);
        $this->assertNotSame($originalEncrypted, File::get($this->store->absolutePath()));
    }

    public function test_failed_replacement_preserves_previous_valid_token(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('Directory permission rename guard requires Unix.');
        }

        $this->store->save($this->sampleRecord('existing-valid-token'));
        $path = $this->store->absolutePath();
        $directory = dirname($path);
        $previousEncrypted = File::get($path);

        chmod($directory, 0555);

        try {
            $this->expectException(RuntimeException::class);
            $this->store->save($this->sampleRecord('should-not-persist'));
        } finally {
            chmod($directory, $this->store->directoryMode());
        }

        $this->assertSame($previousEncrypted, File::get($path));
        $this->assertSame('existing-valid-token', $this->store->load()?->token);
        $this->assertEmpty(glob($directory.'/auth-token.*.tmp') ?: []);
    }

    public function test_invalid_payload_is_rejected_without_touching_existing_token(): void
    {
        $this->store->save($this->sampleRecord('existing-valid-token'));
        $path = $this->store->absolutePath();
        $previousEncrypted = File::get($path);

        $atomicReplace = (new ReflectionClass($this->store))->getMethod('atomicReplace');
        $atomicReplace->setAccessible(true);

        try {
            $this->expectException(RuntimeException::class);
            $atomicReplace->invoke($this->store, $path, 'not-a-valid-encrypted-payload');
        } finally {
            $this->assertSame($previousEncrypted, File::get($path));
            $this->assertSame('existing-valid-token', $this->store->load()?->token);
        }
    }

    public function test_invalidated_record_is_not_valid(): void
    {
        Config::set('suppliers.al_haider.token_expiry_margin_seconds', 60);

        $this->store->save($this->sampleRecord('secret-bearer-token')->invalidated(time()));

        $this->assertFalse($this->store->hasValidToken());
    }

    private function sampleRecord(string $token): AlHaiderTokenRecord
    {
        return new AlHaiderTokenRecord(
            token: $token,
            issuedAt: time(),
            expiresAt: time() + 3600,
            source: 'manual',
        );
    }

    private function clearTokenArtifacts(): void
    {
        $path = $this->store->absolutePath();
        $directory = dirname($path);

        if (is_file($path)) {
            File::delete($path);
        }

        foreach (glob($directory.'/auth-token.*.tmp') ?: [] as $tempFile) {
            if (is_file($tempFile)) {
                File::delete($tempFile);
            }
        }

        if (is_dir($directory) && count(glob($directory.'/*') ?: []) === 0) {
            @rmdir($directory);
        }
    }
}
