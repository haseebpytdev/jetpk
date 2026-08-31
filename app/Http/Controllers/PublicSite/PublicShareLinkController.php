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

        $packageId = data_get($link->payload, 'package_id');
        if (is_string($packageId) && $packageId !== '') {
            return redirect()->to(url('/groups/'.$packageId));
        }

        return redirect()->to(url('/groups'));
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
}
