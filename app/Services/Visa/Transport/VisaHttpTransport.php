<?php

namespace App\Services\Visa\Transport;

/**
 * Isolated HTTP transport for visa providers (live or fixture).
 */
interface VisaHttpTransport
{
    /**
     * @param  array<string, string>  $headers
     * @return array{status:int, headers:array<string,string>, body:string, final_url:string}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        bool $followRedirects = false,
    ): array;
}
