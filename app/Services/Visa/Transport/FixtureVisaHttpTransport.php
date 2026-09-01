<?php

namespace App\Services\Visa\Transport;

use App\Services\Visa\Support\VisaHostAllowlist;
use RuntimeException;

/**
 * Deterministic fixture transport — no external MOFA network calls.
 */
final class FixtureVisaHttpTransport implements VisaHttpTransport
{
    private string $scenario = 'success';

    public function __construct(
        private readonly VisaHostAllowlist $allowlist,
        private readonly string $fixtureRoot,
    ) {}

    public function setScenario(string $scenario): void
    {
        $this->scenario = $scenario;
    }

    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        bool $followRedirects = false,
    ): array {
        $this->allowlist->assertSafeUrl($url);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        if (strtoupper($method) === 'GET' && str_starts_with($path, '/Base/GetRandomCaptchaImage')) {
            return $this->captchaResponse();
        }

        if (strtoupper($method) === 'GET' && $path === '/visaservices/searchvisa') {
            return $this->fileResponse('lookup-page.html', 200, $url);
        }

        if (strtoupper($method) === 'POST' && $path === '/visaservices/searchvisa') {
            return $this->postLookup($body ?? '');
        }

        if (strtoupper($method) === 'GET' && $path === '/Home/PrintedUmrahVisa') {
            if ($this->scenario === 'success') {
                return $this->fileResponse('printed-umrah-visa.html', 200, $url);
            }

            return [
                'status' => 302,
                'headers' => ['location' => '/'],
                'body' => '<title>Object moved</title>',
                'final_url' => $url,
            ];
        }

        throw new RuntimeException('Fixture route not defined for '.$method.' '.$path);
    }

    private function postLookup(string $body): array
    {
        parse_str($body, $fields);
        $captcha = (string) ($fields['Captcha'] ?? '');

        return match ($this->scenario) {
            'captcha_invalid' => $this->fileResponse('lookup-page.html', 200, 'https://visa.mofa.gov.sa/visaservices/searchvisa'),
            'captcha_expired' => $this->fileResponse('lookup-page.html', 200, 'https://visa.mofa.gov.sa/visaservices/searchvisa'),
            'not_found' => $this->fileResponse('lookup-page.html', 200, 'https://visa.mofa.gov.sa/visaservices/searchvisa'),
            'provider_changed' => [
                'status' => 302,
                'headers' => ['location' => '/Home/UnknownVisaPage'],
                'body' => '',
                'final_url' => 'https://visa.mofa.gov.sa/visaservices/searchvisa',
            ],
            'unavailable' => throw new RuntimeException('simulated unavailable'),
            default => [
                'status' => 302,
                'headers' => ['location' => '/Home/PrintedUmrahVisa'],
                'body' => '',
                'final_url' => 'https://visa.mofa.gov.sa/visaservices/searchvisa',
            ],
        };
    }

    /**
     * @return array{status:int, headers:array<string,string>, body:string, final_url:string}
     */
    private function fileResponse(string $name, int $status, string $url): array
    {
        $path = rtrim($this->fixtureRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
        if (! is_file($path)) {
            throw new RuntimeException('Missing visa fixture: '.$name);
        }
        $body = (string) file_get_contents($path);
        $this->allowlist->assertBodySize($body);

        return [
            'status' => $status,
            'headers' => ['content-type' => 'text/html; charset=utf-8'],
            'body' => $body,
            'final_url' => $url,
        ];
    }

    /**
     * @return array{status:int, headers:array<string,string>, body:string, final_url:string}
     */
    private function captchaResponse(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return [
            'status' => 200,
            'headers' => ['content-type' => 'image/png'],
            'body' => $png !== false ? $png : 'PNG',
            'final_url' => 'https://visa.mofa.gov.sa/Base/GetRandomCaptchaImage/1',
        ];
    }
}
