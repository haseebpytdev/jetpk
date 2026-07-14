<?php

namespace App\Services\Communication;

class NotificationPayloadSanitizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizeForScope(array $payload, string $scope): array
    {
        $sanitized = $this->stripSensitiveKeys($payload);

        if (in_array($scope, ['customer', 'agent'], true)) {
            $sanitized = $this->maskContactValues($sanitized);
            $sanitized = $this->removeInternalValues($sanitized);
        }

        if ($scope === 'staff') {
            unset($sanitized['credentials'], $sanitized['tokens']);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stripSensitiveKeys(array $payload): array
    {
        $blocked = [
            'password',
            'password_confirmation',
            'token',
            'api_token',
            'access_token',
            'refresh_token',
            'supplier_payload',
            'raw_supplier_payload',
            'credentials',
            'secret',
        ];

        foreach ($blocked as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function maskContactValues(array $payload): array
    {
        foreach (['passport_number', 'cnic', 'national_id'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $this->maskTrailing($payload[$key], 3);
            }
        }

        if (isset($payload['email']) && is_string($payload['email'])) {
            $payload['email'] = $this->maskEmail($payload['email']);
        }

        if (isset($payload['phone']) && is_string($payload['phone'])) {
            $payload['phone'] = $this->maskTrailing($payload['phone'], 4);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function removeInternalValues(array $payload): array
    {
        unset(
            $payload['internal_notes'],
            $payload['admin_notes'],
            $payload['commission'],
            $payload['cost_breakdown'],
            $payload['markup_breakdown'],
            $payload['supplier_error']
        );

        return $payload;
    }

    private function maskTrailing(string $value, int $visible): string
    {
        $length = strlen($value);
        if ($length <= $visible) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $visible).substr($value, -$visible);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '' || $local === '') {
            return $this->maskTrailing($email, 3);
        }

        $prefix = substr($local, 0, 1);

        return $prefix.str_repeat('*', max(strlen($local) - 1, 1)).'@'.$domain;
    }
}
