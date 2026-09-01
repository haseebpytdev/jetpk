<?php

namespace App\Services\Visa\Support;

final class SaudiMofaResultParser
{
    /**
     * Extract structured fields by English labels from PrintedUmrahVisa HTML.
     *
     * @return array<string, string|null>
     */
    public function parse(string $html): array
    {
        $map = [
            'visa_number' => 'Visa No.',
            'date_of_issue' => 'Date of Issue',
            'valid_until' => 'Valid Until',
            'duration_of_stay' => 'Duration of Stay',
            'passport_number' => 'Passport No.',
            'place_of_issue' => 'Place of issue',
            'name' => 'Name',
            'birth_date' => 'Birth Date',
            'nationality' => 'Nationality',
            'visa_type' => 'Type Of Visa',
            'umrah_operator' => 'Umrah Operator',
            'external_agent' => 'External Agent',
            'application_number' => 'Application No.',
        ];

        $fields = [];
        foreach ($map as $key => $label) {
            $fields[$key] = $this->valueNearLabel($html, $label);
        }

        return $fields;
    }

    private function valueNearLabel(string $html, string $label): ?string
    {
        $quoted = preg_quote($label, '/');
        // Common MOFA layout: value node immediately before the English label node.
        if (preg_match('/>([^<]{1,120})<\s*\/[^>]+>\s*<[^>]+>\s*'.$quoted.'/u', $html, $m)) {
            $value = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($value !== '' && ! str_contains($value, 'رقم')) {
                return $value;
            }
        }
        // Fallback: value node immediately after the English label node.
        if (preg_match('/'.$quoted.'\s*<\/[^>]+>\s*<[^>]+>([^<]{1,120})</u', $html, $m)) {
            $value = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $value !== '' ? $value : null;
        }

        return null;
    }
}
