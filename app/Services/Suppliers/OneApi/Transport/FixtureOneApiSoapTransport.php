<?php

namespace App\Services\Suppliers\OneApi\Transport;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Models\SupplierConnection;
use App\Support\OneApi\OneApiFixtureTransportScope;

/**
 * Fixture SOAP transport for PHPUnit and explicit fixture CLI/matrix flows.
 */
class FixtureOneApiSoapTransport implements OneApiSoapTransportContract
{
    /** @var array<string, list<string>> */
    private array $cookieJars = [];

    public function __construct(
        private readonly OneApiXmlParser $xmlParser,
    ) {}

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function call(
        SupplierConnection $connection,
        string $operation,
        string $requestXml,
        string $workflowSessionKey,
        array $diagnosticContext = [],
    ): array {
        if (! OneApiFixtureTransportScope::isEnabled()) {
            throw new \App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException(
                'fixture_forbidden',
                422,
                'Fixture transport is not available in this runtime context.',
            );
        }

        $fixturePath = (string) ($diagnosticContext['fixture_path'] ?? '');
        $paths = is_array($diagnosticContext['fixture_paths'] ?? null) ? $diagnosticContext['fixture_paths'] : [];
        if (isset($paths[$operation]) && is_string($paths[$operation]) && $paths[$operation] !== '') {
            $fixturePath = $paths[$operation];
        }

        $fixtureKey = (string) ($diagnosticContext['fixture_key'] ?? '');
        if ($fixturePath === '' && $fixtureKey !== '') {
            $fixturePath = OneApiFixtureCaseCatalog::resolvePath($fixtureKey);
        }

        if ($fixturePath !== '') {
            $fixturePath = OneApiFixtureTransportScope::resolveReadableFixturePath($fixturePath);
        }

        if ($fixturePath === '') {
            throw new \App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException(
                'fixture_forbidden',
                422,
                'Fixture path is required for fixture SOAP transport.',
            );
        }

        $body = (string) file_get_contents($fixturePath);
        $this->seedFixtureSessionCookie($workflowSessionKey);

        return $this->xmlParser->parse($body);
    }

    /**
     * @return list<string>
     */
    public function cookiesForSession(string $sessionKey): array
    {
        return $this->cookieJars[$sessionKey] ?? [];
    }

    private function seedFixtureSessionCookie(string $sessionKey): void
    {
        if ($sessionKey === '') {
            return;
        }
        $this->cookieJars[$sessionKey] = array_values(array_unique(array_merge(
            $this->cookieJars[$sessionKey] ?? [],
            ['JSESSIONID=FIXTURE_SESSION_MASKED'],
        )));
    }
}
