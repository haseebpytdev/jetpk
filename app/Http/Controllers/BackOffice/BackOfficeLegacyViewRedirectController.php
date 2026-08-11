<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardBookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\Branding\PlatformBrandingResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Retires legacy Blade booking list/show GET routes in favour of the Next dashboard shell.
 * Mutation and JSON data routes remain on Laravel controllers.
 */
final class BackOfficeLegacyViewRedirectController extends Controller
{
    public function adminBookingsIndex(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        if ($request->string('product')->toString() === 'group') {
            return redirect()->route('admin.group-bookings.index', [
                'q' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ]);
        }

        $previewParam = $request->string('preview')->toString();
        if ($previewParam !== '') {
            $this->assertPreviewBookingAccessible($request->user(), $previewParam);
        }

        return redirect()->to($this->bookingsIndexPath('admin', $request));
    }

    public function adminBookingShow(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);

        return redirect()->to($this->bookingShowPath('admin', $booking, $request));
    }

    public function staffBookingsIndex(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        $previewParam = $request->string('preview')->toString();
        if ($previewParam !== '') {
            $this->assertPreviewBookingAccessible($request->user(), $previewParam);
        }

        return redirect()->to($this->bookingsIndexPath('staff', $request));
    }

    public function staffBookingShow(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);

        return redirect()->to($this->bookingShowPath('staff', $booking, $request));
    }

    private function assertPreviewBookingAccessible(?User $user, string $previewParam): void
    {
        if ($user === null) {
            abort(403);
        }

        $baseQuery = $this->scopedBookingsQuery($user);
        $match = ctype_digit($previewParam)
            ? (clone $baseQuery)->whereKey((int) $previewParam)->first()
            : (clone $baseQuery)->whereIn('booking_reference', PlatformBrandingResolver::lookupReferenceCandidates($previewParam))->first();

        if ($match === null) {
            abort(403);
        }

        Gate::authorize('view', $match);
    }

    private function bookingsIndexPath(string $portal, Request $request): string
    {
        $query = $this->remapBookingsQuery($request->query());

        return $this->pathWithQuery("/{$portal}/dashboard/bookings", $query);
    }

    private function bookingShowPath(string $portal, Booking $booking, Request $request): string
    {
        $publicId = DashboardBookingResource::publicId($booking);
        $query = $this->remapBookingsQuery($request->query());

        return $this->pathWithQuery("/{$portal}/dashboard/bookings/{$publicId}", $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function remapBookingsQuery(array $query): array
    {
        if (isset($query['preview']) && ! isset($query['q'])) {
            $query['q'] = $query['preview'];
            unset($query['preview']);
        }

        if (isset($query['search']) && ! isset($query['q'])) {
            $query['q'] = $query['search'];
            unset($query['search']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function pathWithQuery(string $path, array $query): string
    {
        $filtered = array_filter($query, static fn ($value) => $value !== null && $value !== '');

        if ($filtered === []) {
            return $path;
        }

        return $path.'?'.http_build_query($filtered);
    }

    private function scopedBookingsQuery(User $user): Builder
    {
        $query = Booking::query()->orderByDesc('created_at');

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }
}
