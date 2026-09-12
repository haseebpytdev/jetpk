<?php

namespace App\Support\Emails;

use App\Models\AgentApplication;
use App\Models\Booking;

/**
 * Central operational/customer email subject conventions for bookings and applications.
 */
final class EmailOperationalSubjectFormatter
{
    public static function customerBooking(string $eventLabel, Booking $booking): string
    {
        $brand = self::brandPrefix();

        return trim($brand.' — '.$eventLabel.' — '.$booking->reference_code);
    }

    public static function adminBookingNew(Booking $booking): string
    {
        $pnr = trim((string) ($booking->pnr ?? ''));
        $suffix = $pnr !== '' ? ' — '.$pnr : '';

        return EmailRecipientRoleSubjectTagger::apply(
            'New Booking — '.$booking->reference_code.$suffix,
            'admin',
        );
    }

    public static function adminBookingStatusChanged(Booking $booking, ?string $previousStatus = null, ?string $newStatus = null): string
    {
        $transition = self::statusTransitionLabel($previousStatus, $newStatus);

        return EmailRecipientRoleSubjectTagger::apply(
            'Booking Status Updated — '.$booking->reference_code.($transition !== '' ? ' — '.$transition : ''),
            'admin',
        );
    }

    public static function adminAgentApplication(AgentApplication $application, string $eventLabel): string
    {
        $reference = trim((string) ($application->application_reference ?? ''));
        $agency = trim((string) $application->company_name);
        $parts = array_filter([$eventLabel, $agency !== '' ? $agency : null, $reference !== '' ? $reference : null]);

        return EmailRecipientRoleSubjectTagger::apply(implode(' — ', $parts), 'admin');
    }

    public static function agentApplication(AgentApplication $application, string $eventLabel): string
    {
        $reference = trim((string) ($application->application_reference ?? ''));
        $agency = trim((string) $application->company_name);
        $parts = array_filter([$eventLabel, $agency !== '' ? $agency : null, $reference !== '' ? $reference : null]);

        return EmailRecipientRoleSubjectTagger::apply(implode(' — ', $parts), 'agent');
    }

    public static function staffBookingAttention(Booking $booking, string $eventLabel): string
    {
        return EmailRecipientRoleSubjectTagger::apply(
            $eventLabel.' — '.$booking->reference_code,
            'staff',
        );
    }

    public static function applyRoleTag(string $subject, ?string $recipientType): string
    {
        return EmailRecipientRoleSubjectTagger::apply($subject, $recipientType);
    }

    private static function brandPrefix(): string
    {
        $fromConfig = trim((string) config('app.name', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return 'JetPakistan';
    }

    private static function statusTransitionLabel(?string $previousStatus, ?string $newStatus): string
    {
        $previous = trim((string) $previousStatus);
        $new = trim((string) $newStatus);
        if ($previous === '' || $new === '') {
            return $new !== '' ? ucwords(str_replace('_', ' ', $new)) : '';
        }

        return ucwords(str_replace('_', ' ', $previous)).' → '.ucwords(str_replace('_', ' ', $new));
    }
}
