<?php

namespace App\Services\Visa\Support;

final class VisaRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'passport', 'visa', 'application', 'name', 'dob', 'birth', 'captcha',
        'cookie', 'csrf', 'token', 'first_value', 'second_value', 'tbFirst', 'tbSecond',
    ];

    public function maskIdentifier(?string $value, int $keepStart = 2, int $keepEnd = 2): string
    {
        $value = (string) $value;
        $len = mb_strlen($value);
        if ($len <= ($keepStart + $keepEnd)) {
            return str_repeat('*', max(4, $len));
        }

        return mb_substr($value, 0, $keepStart).str_repeat('*', max(4, $len - $keepStart - $keepEnd)).mb_substr($value, -$keepEnd);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redactContext(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $k = strtolower((string) $key);
            $sensitive = false;
            foreach (self::SENSITIVE_KEYS as $needle) {
                if (str_contains($k, $needle)) {
                    $sensitive = true;
                    break;
                }
            }
            if ($sensitive) {
                $out[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redactContext($value);
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public function stripScripts(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('#\son[a-z]+\s*=\s*("|\')[^"\']*\1#i', '', $html) ?? '';
        $html = preg_replace('#javascript:#i', '', $html) ?? '';

        return $html;
    }
}
