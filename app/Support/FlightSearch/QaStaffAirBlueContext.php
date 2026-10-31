<?php

namespace App\Support\FlightSearch;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Server-side session context for staff/platform-admin AirBlue QA search.
 *
 * Parallel to {@see \App\Support\Booking\AgentBookingContext}; never merges into public_guest.
 */
final class QaStaffAirBlueContext
{
    public const SESSION_KEY = 'ota_qa_staff_airblue_context';

    public const SOURCE_CHANNEL = 'qa_staff_airblue';

    public const SCOPE_TYPE = AirBlueQaSearchScope::SCOPE_TYPE;

    public static function actorIsAllowed(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isPlatformAdmin() || $user->isStaff();
    }

    public static function activate(Request $request, User $user): void
    {
        if (! self::actorIsAllowed($user)) {
            throw new HttpException(403, 'AirBlue QA access requires platform admin or staff.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'source_channel' => self::SOURCE_CHANNEL,
            'qa_scope' => true,
            'qa_scope_type' => self::SCOPE_TYPE,
            'qa_connection_id' => (int) config('ota.qa_staff_airblue.connection_id', 23),
            'qa_actor_user_id' => (int) $user->id,
            'qa_created_at' => now()->toIso8601String(),
            'qa_expires_at' => now()->addSeconds(max(300, (int) config('ota.qa_staff_airblue.session_ttl_seconds', 3600)))->toIso8601String(),
        ]);
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function isActive(Request $request): bool
    {
        return self::resolve($request) !== null;
    }

    /**
     * @return array{
     *     source_channel: string,
     *     qa_scope: bool,
     *     qa_scope_type: string,
     *     qa_connection_id: int,
     *     qa_actor_user_id: int,
     *     qa_created_at: string,
     *     qa_expires_at: string
     * }|null
     */
    public static function resolve(Request $request): ?array
    {
        $raw = $request->session()->get(self::SESSION_KEY);
        if (! is_array($raw)) {
            return null;
        }

        if (($raw['source_channel'] ?? null) !== self::SOURCE_CHANNEL) {
            return null;
        }

        $user = $request->user();
        if (! self::actorIsAllowed($user instanceof User ? $user : null)) {
            return null;
        }

        $actorId = (int) ($raw['qa_actor_user_id'] ?? 0);
        if ($actorId <= 0 || ! $user instanceof User || (int) $user->id !== $actorId) {
            return null;
        }

        $expiresAt = (string) ($raw['qa_expires_at'] ?? '');
        if ($expiresAt !== '' && now()->greaterThan(now()->parse($expiresAt))) {
            self::clear($request);

            return null;
        }

        return [
            'source_channel' => self::SOURCE_CHANNEL,
            'qa_scope' => true,
            'qa_scope_type' => (string) ($raw['qa_scope_type'] ?? self::SCOPE_TYPE),
            'qa_connection_id' => (int) ($raw['qa_connection_id'] ?? config('ota.qa_staff_airblue.connection_id', 23)),
            'qa_actor_user_id' => $actorId,
            'qa_created_at' => (string) ($raw['qa_created_at'] ?? ''),
            'qa_expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{
     *     agency: Agency|null,
     *     source_channel: string,
     *     agent_id: int|null,
     *     agent: null,
     *     agent_booking_mode: bool,
     *     qa_staff_airblue: bool,
     *     qa_connection_id: int|null
     * }
     */
    /**
     * Call only when {@see self::isActive()} is true.
     *
     * @return array{
     *     agency: Agency|null,
     *     source_channel: string,
     *     agent_id: int|null,
     *     agent: null,
     *     agent_booking_mode: bool,
     *     qa_staff_airblue: bool,
     *     qa_connection_id: int
     * }
     */
    public static function resolveCheckoutChannel(Request $request): array
    {
        $context = self::resolve($request);
        if ($context === null) {
            throw new HttpException(403, 'AirBlue QA session is not active.');
        }

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();

        return [
            'agency' => $agency,
            'source_channel' => self::SOURCE_CHANNEL,
            'agent_id' => null,
            'agent' => null,
            'agent_booking_mode' => false,
            'qa_staff_airblue' => true,
            'qa_connection_id' => $context['qa_connection_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function payloadIsQaScope(array $payload): bool
    {
        if (($payload['qa_scope'] ?? false) === true) {
            return true;
        }

        $channel = (string) ($payload['source_channel']
            ?? data_get($payload, 'criteria_cache_context.source_channel')
            ?? '');

        return $channel === self::SOURCE_CHANNEL;
    }
}
