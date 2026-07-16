@php
    use Illuminate\Support\Facades\Route;

    $jpErrorSlug = function_exists('ota_single_client_root_slug')
        ? (ota_single_client_root_slug() ?? (request_client_slug_for_errors() ?? 'jetpk'))
        : (request_client_slug_for_errors() ?? 'jetpk');
    $jpHomeUrl = '/';
    $jpLoginUrl = '/login';
    $jpSupportUrl = Route::has('support') ? '/support' : '/lookup-booking';
    $jpBrandName = client_branding()->companyName();
    $jpTagline = 'Book flights, manage bookings, and travel with confidence.';
    $jpSupportEmail = 'ota@jetpakistan.pk';
    $jpSupportPhone = '';

    try {
        if (function_exists('client_route')) {
            $jpHomeUrl = client_route('home', [], $jpErrorSlug);
            $jpLoginUrl = client_route('login', [], $jpErrorSlug);
            $jpSupportUrl = Route::has('support')
                ? client_route('support', [], $jpErrorSlug)
                : client_route('lookup-booking', [], $jpErrorSlug);
        }
        $jpUsesClientBranding = (function_exists('ota_single_client_root_slug') && ota_single_client_root_slug() === 'jetpk')
            || (function_exists('is_client_preview') && is_client_preview());
        if ($jpUsesClientBranding && function_exists('client_branding')) {
            $jpBrandName = client_branding()->companyName() ?: $jpBrandName;
            $jpSupportEmail = client_branding()->email() ?: $jpSupportEmail;
            $legacyJetpkEmails = ['support@haseebasif.com', 'ticketingjp@jetpakistan.com', 'support@jetpakistan.com'];
            if (in_array(strtolower(trim($jpSupportEmail)), $legacyJetpkEmails, true)) {
                $jpSupportEmail = 'ota@jetpakistan.pk';
            }
            $configuredPhone = trim((string) client_branding()->phone());
            if ($configuredPhone !== '' && ! preg_match('/^(123|\\+92\\s*300\\s*0{6}|\\+92\\s*21\\s*111\\s*000\\s*000)$/i', $configuredPhone)) {
                $jpSupportPhone = $configuredPhone;
            }
        }
    } catch (\Throwable $e) {
        // Error pages must render even when client context is partial.
    }

    $jpErrorHeading = $errorHeading ?? 'Something went wrong';
    $jpErrorMessage = $errorMessage ?? 'We could not complete your request right now. Please try again or contact JetPakistan support.';
    $jpShowBack = ! empty($showRetry) && url()->previous() !== url()->current();
@endphp

<section class="jp-page jp-page--error" aria-labelledby="jp-error-heading">
  <div class="wrap jp-page-wrap jp-page-wrap--error">
    <div class="jp-error-panel">
      <p class="jp-error-panel__brand">{{ $jpBrandName }}</p>
      <p class="jp-error-panel__tagline">{{ $jpTagline }}</p>
      <h1 class="jp-error-panel__title" id="jp-error-heading">{{ $jpErrorHeading }}</h1>
      <p class="jp-error-panel__message">{{ $jpErrorMessage }}</p>

      @if ($jpSupportPhone !== '' || $jpSupportEmail !== '')
        <div class="jp-error-contact">
          @if ($jpSupportPhone !== '')
            <a href="tel:{{ preg_replace('/\s+/', '', $jpSupportPhone) }}" class="jp-error-contact__item">{{ $jpSupportPhone }}</a>
          @endif
          @if ($jpSupportPhone !== '' && $jpSupportEmail !== '')
            <span class="jp-error-contact__sep" aria-hidden="true">·</span>
          @endif
          @if ($jpSupportEmail !== '')
            <a href="mailto:{{ $jpSupportEmail }}" class="jp-error-contact__item">{{ $jpSupportEmail }}</a>
          @endif
        </div>
      @endif

      <div class="jp-page-actions jp-error-actions">
        <a href="{{ $jpHomeUrl }}" class="jp-btn jp-btn--primary">Back to home</a>
        <a href="{{ $jpLoginUrl }}" class="jp-btn jp-btn--secondary">Sign in</a>
        <a href="{{ $jpSupportUrl }}" class="jp-btn jp-btn--secondary">Contact support</a>
        @if ($jpShowBack)
          <a href="{{ url()->previous() }}" class="jp-btn jp-btn--secondary">Go back</a>
        @endif
      </div>
    </div>
  </div>
</section>
