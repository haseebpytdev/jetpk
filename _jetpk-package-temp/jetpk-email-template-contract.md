# JetPakistan Email Template Contract

**Phase:** `JETPK-UNIVERSAL-EMAIL-TEMPLATE-SYSTEM-1`
**Client:** `jetpk` / JetPakistan
**Scope:** Views, partials, resolvers, config, preview + audit commands.
**Out of scope:** Supplier, booking, payment, PNR, ticketing, cancellation, refund, and authentication logic are untouched.

---

## 1. Universal base layout

All JetPK emails extend one layout:

```
emails.themes.jetpakistan.layouts.base
```

It provides: hidden preheader, outer wrapper, 640px white card, header, headline + intro, `@yield('content')`, optional CTA, footer, and a sub-footer legal line. Every child view supplies only its `@section('content')`.

---

## 2. `$emailBrand` contract

Produced by `JetpkEmailBrandingResolver::resolve()`. Every key is optional at render time; the layout and partials fall back safely.

| Key | Type | Fallback | Notes |
|---|---|---|---|
| `client_slug` | string | `jetpk` | Always forced to `jetpk`. |
| `brand_name` | string | `JetPakistan` | |
| `legal_name` | string | `brand_name` | Used in copyright line. |
| `logo_url` | string\|null | `null` | Absolute URL. `null` → safe **text** logo, never a broken `<img>`. |
| `home_url` | string | preview URL | Absolutised. |
| `manage_url` | string\|null | `null` | "Manage booking" footer link. |
| `support_email` | string\|null | `support@jetpakistan.com` | Fallback only. |
| `support_phone` | string\|null | `null` | |
| `primary_color` | hex | `#00843D` | JetPK green. |
| `accent_color` | hex | `#F58220` | CTA orange. |
| `text_color` | hex | `#0f2435` | |
| `muted_color` | hex | `#64748b` | |
| `background_color` | hex | `#eef6f9` | |
| `border_color` | hex | `#d9e6ee` | |
| `card_color` | hex | `#ffffff` | |
| `footer_text` | string | see resolver | |
| `address` | string\|null | `null` | Hidden when null. |

**Never** falls back to Master branding. If the client profile is missing, only safe JetPK constants are used.

---

## 3. Per-email variable contract

All variables are optional. Missing values hide their row — they never render a raw placeholder.

| Variable | Type | Used by |
|---|---|---|
| `subjectText` | string | `<title>`, subject line |
| `preheaderText` | string | hidden preview text |
| `headline` | string | `<h1>` |
| `introText` | string | intro paragraph |
| `recipientName` | string | "Hi {name}," greeting |
| `ctaText` / `ctaUrl` | string | CTA button (renders only if **both** present) |
| `booking` | array | booking-summary partial |
| `payment` | array | payment-summary, invoice |
| `passengers` | array | passenger-summary |
| `itinerary` | array | flight-itinerary |
| `support` | array | support-card, support emails |
| `security` | array | otp, sign-in, password-reset |
| `meta` | array | invoice items, refund notes, change summaries |

### `booking`
`reference`, `pnr`, `status`, `payment_status`, `route`, `trip_type`, `passenger_count`, `amount`, `currency`, `payment_deadline`

> If `pnr` is absent and status is not confirmed, the summary shows **"Will appear once confirmed"**.

### `payment`
`amount`, `currency`, `method`, `status`, `reference` (or `transaction_id`), `invoice_number`, `paid_at`, `invoice_url`, `instructions`

> Never contains card data. Transaction reference only.

### `passengers`
List of strings, or list of `['name' => ..., 'type' => ...]`. **Names only** — no passport or document numbers.

### `itinerary`
List of segments: `label`, `from`, `from_name`, `to`, `to_name`, `depart`, `arrive`, `airline`, `flight_no`, `stops`, `baggage`. A single associative segment is auto-wrapped.

### `security`
`otp`, `expiry_minutes`, `context`, `login_time`, `device`, `browser`, `ip`, `location`, `reset_url`

### `support`
`email`, `phone`, `hours`, `ticket_reference`, `subject`, `status`, `response`, `next_action`

### `meta`
`account_type`, `email`, `message`, `alert_type`, `alert_title`, `change_summary`, `refund_info`, `refund_note`, `payment_instructions`, `items[]`, `subtotal`, `taxes`, `fees`, `total`, `customer_name`

---

## 4. Type → view map

| Type key | View |
|---|---|
| `otp` | `…auth.otp` |
| `sign_in_success` | `…auth.sign-in-success` |
| `password_reset` | `…auth.password-reset` |
| `account_created` | `…auth.account-created` |
| `booking_created` | `…booking.booking-created` |
| `booking_pending_manual_payment` | `…booking.booking-pending-manual-payment` |
| `booking_confirmed` | `…booking.booking-confirmed` |
| `booking_failed` | `…booking.booking-failed` |
| `booking_cancelled` | `…booking.booking-cancelled` |
| `booking_updated` | `…booking.booking-updated` |
| `booking_expiring` | `…booking.booking-expiring` |
| `manual_payment_received` | `…payment.manual-payment-received` |
| `payment_success` | `…payment.payment-success` |
| `payment_failed` | `…payment.payment-failed` |
| `invoice` | `…payment.invoice` |
| `refund_requested` | `…payment.refund-requested` |
| `refund_updated` | `…payment.refund-updated` |
| `support_ticket_created` | `…support.support-ticket-created` |
| `support_reply` | `…support.support-reply` |
| `notification` | `…generic.notification` |

`…` = `emails.themes.jetpakistan`. Override any entry in `config/jetpk_email.php` without touching resolver code.

---

## 5. Email-client rules honoured

- Table-based wrapper; inline critical CSS; minimal `<style>` for mobile only.
- System fonts (`Arial, Helvetica, sans-serif`). No web fonts, no JS, no background images, no CSS grid/flex for layout, no CSS variables.
- Max width 640px, 100% on mobile. CTA full-width on mobile.
- Bulletproof VML button for Outlook desktop.
- Body 15–16px, headline 26px, small text ≥12px.
- Logo has meaningful `alt`; no image-only critical information.
- Escaped `{{ }}` output throughout. **No `{!! !!}` anywhere in this package.**

---

## 6. Security & privacy

Not rendered by any template: raw supplier payloads, exception messages, logs, passport numbers, card data, admin notes, supplier credentials, debug URLs.

- **OTP:** real codes come only from the existing backend service. Preview uses a fake code.
- **Booking failed:** shows a failure-safe message; never a supplier error.
- **Manual payment:** bank details render only from configured settings/DB. Nothing is hardcoded in the Blade.
- **Invoice:** transaction reference only.

---

## 7. Integration (for Cursor)

```php
use App\Support\Emails\JetpkEmailViewResolver;
use App\Support\Emails\JetpkEmailBrandingResolver;

$view = JetpkEmailViewResolver::resolve($type, $clientSlug);

if ($clientSlug === 'jetpk' && $view) {
    return view($view, [
        'emailBrand'    => JetpkEmailBrandingResolver::resolve(),
        'recipientName' => $user?->name,
        'subjectText'   => $subject,
        'preheaderText' => $preheader,
        'headline'      => $headline,
        'booking'       => $bookingPayload,   // optional
        'payment'       => $paymentPayload,   // optional
        'passengers'    => $passengerNames,   // optional
        'itinerary'     => $itinerarySegments,// optional
        'support'       => $supportPayload,   // optional
        'security'      => $securityPayload,  // optional
        'meta'          => $metaPayload,      // optional
        'ctaText'       => $ctaText,
        'ctaUrl'        => $ctaUrl,
    ]);
}

// else: keep existing Master/default behaviour, unchanged.
```

**Before going live**, wire `JetpkEmailBrandingResolver::fetchClientProfile()` to the real client/branding lookup. Until then it returns `[]` and emails render on safe JetPK constants + `config/jetpk_email.php` overrides.

---

## 8. Commands

```bash
php artisan ota:jetpk-email-preview --type=otp
php artisan ota:jetpk-email-preview --all
php artisan ota:jetpk-email-template-audit
```

Preview writes to `storage/app/email-previews/jetpk/{type}.html`. Neither command sends email, writes the DB, nor calls suppliers.
