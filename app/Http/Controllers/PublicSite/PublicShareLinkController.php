<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Share\PublicShareLinkService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PublicShareLinkController extends Controller
{
    public function __construct(
        protected PublicShareLinkService $shareLinks,
    ) {}

    public function showFlight(string $code): Response|View
    {
        $link = $this->shareLinks->findByCode($code);
        if ($link === null || $link->link_type !== 'flight_fare') {
            return response()->view('frontend.share.invalid', [
                'kind' => 'flight',
                'message' => 'This fare link is invalid or no longer available.',
            ], 404);
        }

        $resolved = $this->shareLinks->resolveFlight($link);
        if ($resolved['expired']) {
            return response()->view('frontend.share.expired-flight', [
                'link' => $link,
                'resultsUrl' => $resolved['results_url'],
                'displayFare' => $link->display_fare,
                'currency' => $link->display_currency ?: 'PKR',
            ]);
        }

        return redirect()->to($resolved['results_url']);
    }

    public function showGroup(string $code): Response|View
    {
        $link = $this->shareLinks->findByCode($code);
        if ($link === null || $link->link_type !== 'group_offer') {
            return response()->view('frontend.share.invalid', [
                'kind' => 'group',
                'message' => 'This group link is invalid or no longer available.',
            ], 404);
        }

        if ($link->isExpired()) {
            return response()->view('frontend.share.expired-group', [
                'link' => $link,
                'groupsUrl' => url('/groups'),
            ]);
        }

        $payload = is_array($link->payload) ? $link->payload : [];
        $packageId = data_get($payload, 'package_id');
        $packageId = is_string($packageId) ? $packageId : '';
        $continueUrl = $packageId !== '' ? url('/groups/'.$packageId) : url('/groups');
        $origin = (string) ($link->origin ?: data_get($payload, 'origin', ''));
        $destination = (string) ($link->destination ?: data_get($payload, 'destination', ''));
        $routeLabel = ($origin !== '' && $destination !== '')
            ? strtoupper($origin).' → '.strtoupper($destination)
            : (string) data_get($payload, 'route_label', '');
        $seats = data_get($payload, 'seats_available');
        $seatsLabel = is_numeric($seats) ? ((int) $seats).' seats indicated' : (string) data_get($payload, 'availability_label', '');

        return response()->view('frontend.share.group-landing', [
            'title' => (string) (data_get($payload, 'title') ?: 'Group offer'),
            'routeLabel' => $routeLabel,
            'departLabel' => $link->depart_date?->format('D, j M Y') ?: (string) data_get($payload, 'depart_label', ''),
            'seatsLabel' => $seatsLabel,
            'displayFare' => $link->display_fare,
            'currency' => $link->display_currency ?: 'PKR',
            'expiresLabel' => $link->expires_at?->timezone(config('app.timezone'))->format('j M Y H:i'),
            'continueUrl' => $continueUrl,
            'groupsUrl' => url('/groups'),
        ]);
    }

    public function createFlight(Request $request): Response
    {
        $this->shareLinks->assertCreateRateLimit($request->ip() ?? 'unknown');

        $data = $request->validate([
            'origin' => ['required', 'string', 'max:8'],
            'destination' => ['required', 'string', 'max:8'],
            'depart_date' => ['required', 'date'],
            'return_date' => ['nullable', 'date'],
            'trip_type' => ['nullable', 'string', 'max:32'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:9'],
            'children' => ['nullable', 'integer', 'min:0', 'max:9'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:9'],
            'cabin' => ['nullable', 'string', 'max:32'],
            'display_currency' => ['nullable', 'string', 'max:8'],
            'display_fare' => ['nullable', 'numeric', 'min:0'],
            'airline_code' => ['nullable', 'string', 'max:8'],
            'airline_name' => ['nullable', 'string', 'max:120'],
            'offer_fingerprint' => ['nullable', 'string', 'max:64'],
            'supplier_offer_expires_at' => ['nullable', 'date'],
        ]);

        $link = $this->shareLinks->createFlightLink($data, 'public_api');

        return response()->json([
            'ok' => true,
            'code' => $link->code,
            'url' => url($link->publicPath()),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'revalidation_notice' => 'Fare will be revalidated before booking.',
        ]);
    }

    public function createGroup(Request $request): Response
    {
        $this->shareLinks->assertCreateRateLimit($request->ip() ?? 'unknown');

        $data = $request->validate([
            'package_id' => ['required', 'string', 'max:64'],
            'origin' => ['nullable', 'string', 'max:8'],
            'destination' => ['nullable', 'string', 'max:8'],
            'depart_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'display_currency' => ['nullable', 'string', 'max:8'],
            'display_fare' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:160'],
            'route_label' => ['nullable', 'string', 'max:120'],
            'seats_available' => ['nullable', 'integer', 'min:0', 'max:999'],
            'availability_label' => ['nullable', 'string', 'max:120'],
        ]);

        $payload = [
            'package_id' => $data['package_id'],
            'title' => $data['title'] ?? null,
            'route_label' => $data['route_label'] ?? null,
            'seats_available' => $data['seats_available'] ?? null,
            'availability_label' => $data['availability_label'] ?? null,
            'origin' => $data['origin'] ?? null,
            'destination' => $data['destination'] ?? null,
        ];

        $link = $this->shareLinks->createGroupLink([
            'origin' => $data['origin'] ?? null,
            'destination' => $data['destination'] ?? null,
            'depart_date' => $data['depart_date'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'display_currency' => $data['display_currency'] ?? 'PKR',
            'display_fare' => $data['display_fare'] ?? null,
            'payload' => $payload,
        ], 'public_api');

        return response()->json([
            'ok' => true,
            'code' => $link->code,
            'url' => url($link->publicPath()),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'revalidation_notice' => 'Group availability will be revalidated before booking.',
        ]);
    }
}
