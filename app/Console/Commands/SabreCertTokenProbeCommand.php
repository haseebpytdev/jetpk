<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\Diagnostics\SabreCertTokenProbe;
use Illuminate\Console\Command;

class SabreCertTokenProbeCommand extends Command
{
    protected $signature = 'sabre:cert-token-probe
                            {--profile= : CERT/STL credential profile (e.g. cert_6md8, cert_lu6k, cert_test3)}
                            {--auth-url= : Override OAuth token URL (host/path only in output)}
                            {--encoding-style=sabre_epr_encoded_current : Authorization encoding variant (sabre_epr_encoded_current, raw_basic, encoded_epr_raw_secret, raw_epr_encoded_secret)}';

    protected $description = 'Probe Sabre CERT/STL OAuth token for env-only manager credentials (no booking/cancel/ticketing)';

    public function handle(SabreCertTokenProbe $probe): int
    {
        $profile = $this->option('profile');

        if (! is_string($profile) || trim($profile) === '') {
            $available = $probe->configuredProfileKeys();
            $this->components->error('Missing required --profile option.');
            if ($available !== []) {
                $this->line('configured_profiles='.implode(',', $available));
            }

            return self::FAILURE;
        }

        $authUrl = $this->option('auth-url');
        $encodingStyle = $this->option('encoding-style');

        $result = $probe->probe(
            trim($profile),
            is_string($authUrl) && trim($authUrl) !== '' ? trim($authUrl) : null,
            is_string($encodingStyle) && trim($encodingStyle) !== ''
                ? trim($encodingStyle)
                : 'sabre_epr_encoded_current',
        );

        foreach ($this->formatLines($result) as $line) {
            $this->line($line);
        }

        return ($result['token_present'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{
     *     profile: string,
     *     auth_host: string,
     *     auth_path: string,
     *     encoding_style: string,
     *     http_status: int,
     *     token_present: bool,
     *     token_type: string|null,
     *     expires_in: int|null,
     *     error_code: string|null,
     *     error_message: string|null,
     *     pcc_present: bool,
     *     domain_present: bool,
     * }  $result
     * @return list<string>
     */
    private function formatLines(array $result): array
    {
        $lines = [
            'profile='.$result['profile'],
            'auth_host='.$result['auth_host'],
            'auth_path='.$result['auth_path'],
            'encoding_style='.$result['encoding_style'],
            'http_status='.$result['http_status'],
            'token_present='.(($result['token_present'] ?? false) ? 'true' : 'false'),
            'pcc_present='.(($result['pcc_present'] ?? false) ? 'true' : 'false'),
            'domain_present='.(($result['domain_present'] ?? false) ? 'true' : 'false'),
        ];

        if (isset($result['token_type']) && is_string($result['token_type']) && $result['token_type'] !== '') {
            $lines[] = 'token_type='.$result['token_type'];
        }

        if (array_key_exists('expires_in', $result) && $result['expires_in'] !== null) {
            $lines[] = 'expires_in='.$result['expires_in'];
        }

        if (isset($result['error_code']) && is_string($result['error_code']) && $result['error_code'] !== '') {
            $lines[] = 'error_code='.$result['error_code'];
        }

        if (isset($result['error_message']) && is_string($result['error_message']) && $result['error_message'] !== '') {
            $lines[] = 'error_message='.$result['error_message'];
        }

        return $lines;
    }
}
