<?php

namespace App\Services\Communication;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Support\Emails\OperationalEmailDefaults;

/**
 * Seeds missing agency_message_templates rows from OperationalEmailDefaults (K2D-A / K2D-B3).
 * Creates missing rows only unless $force is true; never overwrites customized copy by default.
 */
class AgencyMessageTemplateSeeder
{
    /**
     * @return array{created: int, skipped_existing: int, skipped_no_defaults: int, updated: int}
     */
    public function seedAllDefaultsForAgency(Agency $agency, bool $force = false, bool $dryRun = false): array
    {
        return $this->mergeStats(
            $this->seedBusinessDefaultsForAgency($agency, $force, $dryRun),
            $this->seedAuthSecurityDefaultsForAgency($agency, $force, $dryRun),
        );
    }

    /**
     * @return array{created: int, skipped_existing: int, skipped_no_defaults: int, updated: int}
     */
    public function seedBusinessDefaultsForAgency(Agency $agency, bool $force = false, bool $dryRun = false): array
    {
        return $this->seedEventKeysForAgency(
            $agency,
            OperationalEmailDefaults::BUSINESS_OPERATIONAL_EVENT_KEYS,
            $force,
            $dryRun,
        );
    }

    /**
     * @return array{created: int, skipped_existing: int, skipped_no_defaults: int, updated: int}
     */
    public function seedAuthSecurityDefaultsForAgency(Agency $agency, bool $force = false, bool $dryRun = false): array
    {
        return $this->seedEventKeysForAgency(
            $agency,
            OperationalEmailDefaults::AUTH_SECURITY_EVENT_KEYS,
            $force,
            $dryRun,
        );
    }

    /**
     * @param  list<string>  $eventKeys
     * @return array{created: int, skipped_existing: int, skipped_no_defaults: int, updated: int}
     */
    protected function seedEventKeysForAgency(
        Agency $agency,
        array $eventKeys,
        bool $force,
        bool $dryRun,
    ): array {
        $stats = [
            'created' => 0,
            'skipped_existing' => 0,
            'skipped_no_defaults' => 0,
            'updated' => 0,
        ];

        foreach ($eventKeys as $eventKey) {
            $defaults = OperationalEmailDefaults::forEvent($eventKey);
            if ($defaults === null) {
                $stats['skipped_no_defaults']++;

                continue;
            }

            $existing = AgencyMessageTemplate::query()
                ->where('agency_id', $agency->id)
                ->where('event', $eventKey)
                ->where('channel', 'email')
                ->first();

            if ($existing !== null && ! $force) {
                $stats['skipped_existing']++;

                continue;
            }

            if ($dryRun) {
                if ($existing === null) {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }

                continue;
            }

            AgencyMessageTemplate::query()->updateOrCreate(
                [
                    'agency_id' => $agency->id,
                    'event' => $eventKey,
                    'channel' => 'email',
                ],
                [
                    'subject' => $defaults['subject'],
                    'body' => $defaults['body'],
                    'is_enabled' => true,
                    'variables' => OperationalEmailDefaults::variablesForEvent($eventKey),
                ],
            );

            if ($existing === null) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array{created: int, skipped_existing: int, skipped_no_defaults: int, updated: int}  $first
     * @param  array{created: int, skipped_existing: int, skipped_no_defaults: int, updated: int}  $second
     * @return array{created: int, skipped_existing: int, skipped_no_defaults: int, updated: int}
     */
    protected function mergeStats(array $first, array $second): array
    {
        return [
            'created' => $first['created'] + $second['created'],
            'skipped_existing' => $first['skipped_existing'] + $second['skipped_existing'],
            'skipped_no_defaults' => $first['skipped_no_defaults'] + $second['skipped_no_defaults'],
            'updated' => $first['updated'] + $second['updated'],
        ];
    }
}
