<?php

namespace App\Support\Booking;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Server-side session context when an authenticated agent/agency user starts a booking
 * via the public flight search + checkout flow.
 */
final class AgentBookingContext
{
    public const SESSION_KEY = 'ota_agent_booking_context';

    public const BOOKING_CONTEXT_AGENT = 'agent';

    public const BOOKING_CHANNEL_AGENT_PORTAL = 'agent_portal';

    public const SOURCE_CHANNEL_AGENT_PORTAL = 'agent_portal';

    public const SOURCE_CHANNEL_PUBLIC_GUEST = 'public_guest';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function activate(Request $request, User $user, array $payload = []): void
    {
        if (! $user->isAgentPortalUser()) {
            throw new HttpException(403, 'Agent portal access required.');
        }

        $agent = $user->agent();
        if ($agent === null) {
            throw new HttpException(403, 'Agent profile required.');
        }

        $agency = $user->currentAgency;
        if ($agency === null || $agent->agency_id !== $agency->id) {
            throw new HttpException(403, 'Agency context required.');
        }

        $request->session()->put(self::SESSION_KEY, array_merge([
            'booking_context' => self::BOOKING_CONTEXT_AGENT,
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'agent_user_id' => $user->id,
            'booking_channel' => self::BOOKING_CHANNEL_AGENT_PORTAL,
            'activated_at' => now()->toIso8601String(),
        ], $payload));
    }

    public static function isActive(Request $request): bool
    {
        $resolved = self::resolve($request);

        return $resolved !== null;
    }

    /**
     * @return array{
     *     booking_context: string,
     *     agency_id: int,
     *     agent_id: int,
     *     agent_user_id: int,
     *     booking_channel: string,
     *     activated_at?: string
     * }|null
     */
    public static function resolve(Request $request): ?array
    {
        $raw = $request->session()->get(self::SESSION_KEY);
        if (! is_array($raw)) {
            return null;
        }

        if (($raw['booking_context'] ?? null) !== self::BOOKING_CONTEXT_AGENT) {
            return null;
        }

        $agencyId = (int) ($raw['agency_id'] ?? 0);
        $agentId = (int) ($raw['agent_id'] ?? 0);
        $agentUserId = (int) ($raw['agent_user_id'] ?? 0);

        if ($agencyId <= 0 || $agentId <= 0 || $agentUserId <= 0) {
            return null;
        }

        return [
            'booking_context' => self::BOOKING_CONTEXT_AGENT,
            'agency_id' => $agencyId,
            'agent_id' => $agentId,
            'agent_user_id' => $agentUserId,
            'booking_channel' => (string) ($raw['booking_channel'] ?? self::BOOKING_CHANNEL_AGENT_PORTAL),
            'activated_at' => isset($raw['activated_at']) ? (string) $raw['activated_at'] : null,
        ];
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function agency(Request $request): ?Agency
    {
        $context = self::resolve($request);
        if ($context === null) {
            return null;
        }

        return Agency::query()->find($context['agency_id']);
    }

    public static function agent(Request $request): ?Agent
    {
        $context = self::resolve($request);
        if ($context === null) {
            return null;
        }

        return Agent::query()->find($context['agent_id']);
    }

    public static function agencyDisplayName(Request $request): ?string
    {
        $agency = self::agency($request);
        if ($agency === null) {
            return null;
        }

        $agency->loadMissing('agencySetting');

        return trim((string) ($agency->agencySetting?->display_name ?: $agency->name)) ?: null;
    }

    /**
     * Resolve agency + channel for public flight search and checkout.
     *
     * @return array{
     *     agency: Agency|null,
     *     source_channel: string,
     *     agent_id: int|null,
     *     agent: Agent|null,
     *     agent_booking_mode: bool
     * }
     */
    public static function resolveCheckoutChannel(Request $request): array
    {
        $context = self::resolve($request);
        if ($context !== null) {
            $agency = Agency::query()->find($context['agency_id']);
            $agent = Agent::query()->find($context['agent_id']);

            if ($agency !== null && $agent !== null && $agent->agency_id === $agency->id) {
                return [
                    'agency' => $agency,
                    'source_channel' => self::SOURCE_CHANNEL_AGENT_PORTAL,
                    'agent_id' => $agent->id,
                    'agent' => $agent,
                    'agent_booking_mode' => true,
                ];
            }
        }

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();

        return [
            'agency' => $agency,
            'source_channel' => self::SOURCE_CHANNEL_PUBLIC_GUEST,
            'agent_id' => null,
            'agent' => null,
            'agent_booking_mode' => false,
        ];
    }
}
