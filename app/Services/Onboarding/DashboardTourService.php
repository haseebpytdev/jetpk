<?php

namespace App\Services\Onboarding;

use App\Models\User;
use App\Support\Onboarding\DashboardTourAuthority;
use App\Support\Onboarding\DashboardTourCatalog;
use InvalidArgumentException;

/**
 * Read/write users.meta.dashboard_tours and present role-filtered tour steps.
 */
class DashboardTourService
{
    public function __construct(
        protected DashboardTourCatalog $catalog,
    ) {}

    /**
     * @return array<string, array{status: string, at: string}>
     */
    public function toursMap(User $user): array
    {
        $meta = is_array($user->meta) ? $user->meta : [];
        $raw = $meta[DashboardTourAuthority::META_KEY] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $entry) {
            if (! is_string($key) || ! is_array($entry)) {
                continue;
            }
            $status = $entry['status'] ?? null;
            $at = $entry['at'] ?? null;
            if (! is_string($status) || ! in_array($status, DashboardTourAuthority::allowedStatuses(), true)) {
                continue;
            }
            if (! is_string($at) || $at === '') {
                continue;
            }
            $out[$key] = ['status' => $status, 'at' => $at];
        }

        return $out;
    }

    /**
     * @return array{tour_key: string, tours: array<string, array{status: string, at: string}>, steps: list<array<string, mixed>>, should_auto_start: bool}
     */
    public function presentForCustomer(User $user): array
    {
        abort_unless($user->isCustomer(), 403);

        return $this->present($user, DashboardTourAuthority::CUSTOMER_TOUR, $this->catalog->stepsForCustomer());
    }

    /**
     * @return array{tour_key: string, tours: array<string, array{status: string, at: string}>, steps: list<array<string, mixed>>, should_auto_start: bool}
     */
    public function presentForAgent(User $user): array
    {
        abort_unless($user->isAgentPortalUser(), 403);

        return $this->present($user, DashboardTourAuthority::AGENT_TOUR, $this->catalog->stepsForAgent($user));
    }

    /**
     * @return array{tour_key: string, tours: array<string, array{status: string, at: string}>, steps: list<array<string, mixed>>, should_auto_start: bool, mini_guides?: list<array<string, mixed>>}
     */
    public function presentForBackOffice(User $user, string $portalType): array
    {
        $portalType = $portalType === 'admin' ? 'admin' : 'staff';

        if ($portalType === 'admin') {
            abort_unless($user->isPlatformAdmin(), 403);
            $payload = $this->present(
                $user,
                DashboardTourAuthority::ADMIN_TOUR,
                $this->catalog->stepsForAdmin(),
            );
            $payload['mini_guides'] = [
                [
                    'tour_key' => DashboardTourAuthority::ADMIN_API_SETTINGS_MINI,
                    'steps' => $this->catalog->stepsForAdminApiSettingsMini(),
                    'should_auto_start' => ! array_key_exists(
                        DashboardTourAuthority::ADMIN_API_SETTINGS_MINI,
                        $payload['tours'],
                    ),
                ],
            ];

            return $payload;
        }

        abort_unless($user->isStaff() || $user->isPlatformAdmin(), 403);

        return $this->present(
            $user,
            DashboardTourAuthority::STAFF_TOUR,
            $this->catalog->stepsForStaff($user),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array{tour_key: string, tours: array<string, array{status: string, at: string}>, steps: list<array<string, mixed>>, should_auto_start: bool}
     */
    private function present(User $user, string $tourKey, array $steps): array
    {
        $tours = $this->toursMap($user);

        return [
            'ok' => true,
            'tour_key' => $tourKey,
            'tours' => $tours,
            'steps' => array_map(static function (array $step): array {
                unset($step['requires_nav']);

                return $step;
            }, $steps),
            'should_auto_start' => ! array_key_exists($tourKey, $tours),
        ];
    }

    /**
     * @return array<string, array{status: string, at: string}>
     */
    public function update(User $user, string $tourKey, ?string $status, bool $restart = false): array
    {
        $this->assertTourKeyAllowedForUser($user, $tourKey);

        $meta = is_array($user->meta) ? $user->meta : [];
        $tours = is_array($meta[DashboardTourAuthority::META_KEY] ?? null)
            ? $meta[DashboardTourAuthority::META_KEY]
            : [];

        if ($restart) {
            unset($tours[$tourKey]);
        } else {
            if ($status === null || ! in_array($status, DashboardTourAuthority::allowedStatuses(), true)) {
                throw new InvalidArgumentException('Invalid tour status.');
            }
            $tours[$tourKey] = [
                'status' => $status,
                'at' => now()->toIso8601String(),
            ];
        }

        $meta[DashboardTourAuthority::META_KEY] = $tours;
        $user->forceFill(['meta' => $meta])->save();

        return $this->toursMap($user->fresh());
    }

    private function assertTourKeyAllowedForUser(User $user, string $tourKey): void
    {
        if (! in_array($tourKey, DashboardTourAuthority::knownTourKeys(), true)) {
            throw new InvalidArgumentException('Unknown tour key.');
        }

        $allowed = match (true) {
            $user->isCustomer() => [DashboardTourAuthority::CUSTOMER_TOUR],
            $user->isAgentPortalUser() => [DashboardTourAuthority::AGENT_TOUR],
            $user->isPlatformAdmin() => [
                DashboardTourAuthority::ADMIN_TOUR,
                DashboardTourAuthority::STAFF_TOUR,
                DashboardTourAuthority::ADMIN_API_SETTINGS_MINI,
            ],
            $user->isStaff() => [DashboardTourAuthority::STAFF_TOUR],
            default => [],
        };

        if (! in_array($tourKey, $allowed, true)) {
            abort(403, 'Tour key is not available for this account type.');
        }
    }
}
