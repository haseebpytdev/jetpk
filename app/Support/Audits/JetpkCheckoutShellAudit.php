<?php

namespace App\Support\Audits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * JetPK checkout shell render checks (8H): progress-bar include + compile smoke.
 */
final class JetpkCheckoutShellAudit
{
  private const PROGRESS_PARTIAL = 'themes.frontend.jetpakistan.components.checkout.progress-bar';

  /** @var list<string> */
  private const CHECKOUT_SHELLS = [
    'resources/views/themes/frontend/jetpakistan/frontend/booking/passenger-details.blade.php',
    'resources/views/themes/frontend/jetpakistan/frontend/booking/review.blade.php',
    'resources/views/themes/frontend/jetpakistan/frontend/booking/card-payment.blade.php',
    'resources/views/themes/frontend/jetpakistan/frontend/booking/confirmation.blade.php',
  ];

  /**
   * @return array{
   *     progress_partial_exists: bool,
   *     shells_use_include: bool,
   *     shells_avoid_invalid_component: bool,
   *     progress_renders: bool,
   *     issues: list<string>,
   *     fail_count: int
   * }
   */
  public function run(): array
  {
    $issues = [];
    $failCount = 0;

    $progressPath = resource_path('views/themes/frontend/jetpakistan/components/checkout/progress-bar.blade.php');
    $progressPartialExists = File::exists($progressPath);
    if (! $progressPartialExists) {
      $issues[] = 'Missing progress-bar partial at resources/views/themes/frontend/jetpakistan/components/checkout/progress-bar.blade.php';
      $failCount++;
    }

    $shellsUseInclude = true;
    $shellsAvoidInvalidComponent = true;

    foreach (self::CHECKOUT_SHELLS as $relativeShell) {
      $shellPath = base_path($relativeShell);
      if (! File::exists($shellPath)) {
        $issues[] = 'Missing checkout shell: '.$relativeShell;
        $shellsUseInclude = false;
        $shellsAvoidInvalidComponent = false;
        $failCount++;

        continue;
      }

      $content = (string) File::get($shellPath);

      if (! str_contains($content, "@include('".self::PROGRESS_PARTIAL."'")) {
        $issues[] = 'Checkout shell missing progress-bar @include: '.$relativeShell;
        $shellsUseInclude = false;
        $failCount++;
      }

      if (str_contains($content, 'x-themes.frontend.jetpakistan.components.checkout.progress-bar')) {
        $issues[] = 'Checkout shell still uses invalid x-component syntax: '.$relativeShell;
        $shellsAvoidInvalidComponent = false;
        $failCount++;
      }
    }

    $progressRenders = false;
    if ($progressPartialExists && View::exists(self::PROGRESS_PARTIAL)) {
      try {
        $html = view(self::PROGRESS_PARTIAL, ['activeStep' => 2])->render();
        $progressRenders = str_contains($html, 'jp-checkout-progress')
          && str_contains($html, 'Passenger details');
      } catch (\Throwable $e) {
        $issues[] = 'progress-bar render failed: '.$e->getMessage();
        $failCount++;
      }
    } else {
      $issues[] = 'progress-bar view not registered: '.self::PROGRESS_PARTIAL;
      $failCount++;
    }

    if ($progressRenders === false && $progressPartialExists) {
      $issues[] = 'progress-bar render missing expected markup';
      $failCount++;
    }

    return [
      'progress_partial_exists' => $progressPartialExists,
      'shells_use_include' => $shellsUseInclude,
      'shells_avoid_invalid_component' => $shellsAvoidInvalidComponent,
      'progress_renders' => $progressRenders,
      'issues' => $issues,
      'fail_count' => $failCount,
    ];
  }
}
