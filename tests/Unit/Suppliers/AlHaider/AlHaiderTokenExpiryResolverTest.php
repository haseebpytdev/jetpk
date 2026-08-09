<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Services\Suppliers\AlHaider\AlHaiderTokenExpiryResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AlHaiderTokenExpiryResolverTest extends TestCase
{
    public function test_uses_expires_in_from_supplier_response(): void
    {
        Config::set('suppliers.al_haider.token_validity_seconds', 31_536_000);

        $issuedAt = 1_700_000_000;
        $response = new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'token' => 'abc',
            'expires_in' => 7200,
        ], JSON_THROW_ON_ERROR)));

        $expiresAt = app(AlHaiderTokenExpiryResolver::class)->resolveExpiresAt($response, $issuedAt);

        $this->assertSame($issuedAt + 7200, $expiresAt);
    }

    public function test_falls_back_to_business_default_validity_when_metadata_missing(): void
    {
        Config::set('suppliers.al_haider.token_validity_seconds', 31_536_000);

        $issuedAt = 1_700_000_000;
        $response = new Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'token' => 'abc',
        ], JSON_THROW_ON_ERROR)));

        $expiresAt = app(AlHaiderTokenExpiryResolver::class)->resolveExpiresAt($response, $issuedAt);

        $this->assertSame($issuedAt + 31_536_000, $expiresAt);
    }
}
