<?php

namespace App\Support\Sabre\Scenario;

use InvalidArgumentException;

/**
 * Loads and validates operator-supplied passenger JSON for {@see SabreGdsLiveScenarioRunner}.
 */
final class SabreGdsLiveScenarioRunnerPassengerLoader
{
    public const REASON_VALIDATION_FAILED = 'passenger_json_validation_failed';

    /** @var list<string> */
    private const REQUIRED = [
        'title',
        'given_name',
        'surname',
        'gender',
        'dob',
        'nationality',
        'country',
        'passport_number',
        'passport_issue_date',
        'passport_expiry_date',
        'phone',
        'email',
    ];

    /**
     * @return array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>,
     *     safe_contact: array{email_present: bool, phone_present: bool, country: string|null}
     * }
     */
    public function loadFromPath(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException('Passenger JSON file not found.');
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || trim($raw) === '') {
            throw new InvalidArgumentException('Passenger JSON file is empty.');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Passenger JSON must decode to an object.');
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>,
     *     safe_contact: array{email_present: bool, phone_present: bool, country: string|null}
     * }
     */
    public function normalize(array $decoded): array
    {
        $missing = [];
        foreach (self::REQUIRED as $key) {
            if (! array_key_exists($key, $decoded) || trim((string) $decoded[$key]) === '') {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw new InvalidArgumentException('Passenger JSON missing required fields: '.implode(', ', $missing));
        }

        $email = strtolower(trim((string) $decoded['email']));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Passenger JSON email is invalid.');
        }

        $passenger = [
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'title' => strtoupper(trim((string) $decoded['title'])),
            'first_name' => trim((string) $decoded['given_name']),
            'last_name' => trim((string) $decoded['surname']),
            'gender' => strtoupper(substr(trim((string) $decoded['gender']), 0, 1)),
            'date_of_birth' => trim((string) $decoded['dob']),
            'nationality' => strtoupper(trim((string) $decoded['nationality'])),
            'country_of_residence' => strtoupper(trim((string) $decoded['country'])),
            'passport_number' => strtoupper(trim((string) $decoded['passport_number'])),
            'passport_issuing_country' => strtoupper(trim((string) $decoded['nationality'])),
            'passport_issue_date' => trim((string) $decoded['passport_issue_date']),
            'passport_expiry_date' => trim((string) $decoded['passport_expiry_date']),
        ];

        $contact = [
            'email' => $email,
            'phone' => trim((string) $decoded['phone']),
            'country' => strtoupper(trim((string) $decoded['country'])),
        ];

        return [
            'passenger' => $passenger,
            'contact' => $contact,
            'safe_contact' => [
                'email_present' => $email !== '',
                'phone_present' => trim((string) $decoded['phone']) !== '',
                'country' => strtoupper(trim((string) $decoded['country'])) ?: null,
            ],
        ];
    }
}
