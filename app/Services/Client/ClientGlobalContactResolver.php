<?php

namespace App\Services\Client;

use App\Support\Client\ClientPageBootstrapTemplate;
use App\Support\Client\ClientPageKeys;

/**
 * Resolves canonical global contact details from CMS global settings with optional page override.
 */
final class ClientGlobalContactResolver
{
    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $pageOverride
     * @return array{phone: string, phone_e164: string, email: string, whatsapp: string, website: string, office: string, hours: string, company_legal_name: string}
     */
    public function contact(array $pageOverride = []): array
    {
        $global = $this->contentFor(ClientPageKeys::GLOBAL);
        $bootstrap = ClientPageBootstrapTemplate::globalContent()['contact'] ?? [];
        $merged = array_merge($bootstrap, is_array($global['contact'] ?? null) ? $global['contact'] : [], $pageOverride);

        return [
            'phone' => trim((string) ($merged['phone'] ?? '')),
            'phone_e164' => trim((string) ($merged['phone_e164'] ?? '')),
            'email' => trim((string) ($merged['email'] ?? '')),
            'whatsapp' => trim((string) ($merged['whatsapp'] ?? '')),
            'website' => trim((string) ($merged['website'] ?? '')),
            'office' => trim((string) ($merged['office'] ?? '')),
            'hours' => trim((string) ($merged['hours'] ?? '')),
            'company_legal_name' => trim((string) ($merged['company_legal_name'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFor(string $pageKey): array
    {
        return $this->contentResolver->contentFor($pageKey);
    }
}
