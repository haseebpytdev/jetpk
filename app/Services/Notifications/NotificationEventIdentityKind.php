<?php

namespace App\Services\Notifications;

enum NotificationEventIdentityKind: string
{
    case OccurrenceScoped = 'OCCURRENCE_SCOPED';
    case OneShotAggregate = 'ONE_SHOT_AGGREGATE';
    case RequiresVariantOrOccurrence = 'REQUIRES_VARIANT_OR_OCCURRENCE';
}
