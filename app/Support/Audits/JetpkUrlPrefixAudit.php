<?php

namespace App\Support\Audits;

use Illuminate\Support\Facades\File;

/**
 * Static JetPK URL prefix isolation checks for auth/results/checkout (8F).
 */
final class JetpkUrlPrefixAudit
{
    /**
     * @return array{
     *     auth_login_form_action: string,
     *     results_runtime_urls: string,
     *     checkout_return_path: string,
     *     fail_count: int,
     *     issues: list<string>
     * }
     */
    public function run(): array
    {
        $issues = [];
        $failCount = 0;

        $loginBlade = (string) File::get(resource_path('views/themes/frontend/jetpakistan/auth/login.blade.php'));
        $authLoginPrefixed = str_contains($loginBlade, "client_url('/login')")
            && ! preg_match("/action=\"\{\{\s*route\('login'\)/", $loginBlade);
        if (! $authLoginPrefixed) {
            $issues[] = 'jetpk login form still uses unprefixed route(login) action';
            $failCount++;
        }

        $resultsPartial = (string) File::get(resource_path('views/frontend/flights/partials/results-page.blade.php'));
        $resultsRuntimePrefixed = str_contains($resultsPartial, 'data-results-url="{{ client_route(')
            && str_contains($resultsPartial, 'data-results-search-url="{{ client_route(')
            && str_contains($resultsPartial, 'resultsSearchUrl +')
            && ! preg_match("/fetch\('\/flights\/results\//", $resultsPartial)
            && ! preg_match("/pushState\([^,]+,\s*'\/flights\/results/", $resultsPartial);
        if (! $resultsRuntimePrefixed) {
            $issues[] = 'results-page still hardcodes /flights/results fetch/pushState paths';
            $failCount++;
        }

        $passengerBody = (string) File::get(resource_path('views/frontend/booking/partials/passenger-details-body.blade.php'));
        $checkoutReturnPrefixed = str_contains($passengerBody, "client_url('/booking/passengers?");
        if (! $checkoutReturnPrefixed) {
            $issues[] = 'checkout return path not client_url-prefixed';
            $failCount++;
        }

        return [
            'auth_login_form_action' => $authLoginPrefixed ? 'client-prefixed' : 'unprefixed',
            'results_runtime_urls' => $resultsRuntimePrefixed ? 'client-prefixed' : 'unprefixed',
            'checkout_return_path' => str_contains($passengerBody, "client_url('/booking/passengers") ? 'client-prefixed' : 'unprefixed',
            'fail_count' => $failCount,
            'issues' => $issues,
        ];
    }
}
