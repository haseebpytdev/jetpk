<?php

namespace App\Support\Emails;

/**
 * Public accessor for JetPK email preview/audit sample payloads.
 */
final class JetpkEmailSampleDataProvider
{
    use JetpkEmailSampleData;

    public static function forType(string $type): array
    {
        return (new self)->sampleData($type);
    }

    public static function forEvent(string $eventKey): array
    {
        $type = JetpkEmailEventContentRegistry::find($eventKey)?->jetpkTypeKey
            ?? JetpkEmailEventTypeMap::typeForEvent($eventKey)
            ?? 'notification';

        return self::forType($type);
    }
}
