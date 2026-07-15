# JetPK Email — Integration Map, QA, SFTP & Rollback

Companion to `docs/email/jetpk-email-template-contract.md`.

---

## 1. Mail class connection map (for Cursor)

Nothing below is applied yet. This package **adds** files only; no existing mail class was modified.

| Existing mail event / class (inspect) | Type key | JetPK view |
|---|---|---|
| OTP / verification code mail | `otp` | `…auth.otp` |
| Login / security notification | `sign_in_success` | `…auth.sign-in-success` |
| Password reset notification | `password_reset` | `…auth.password-reset` |
| Registration / welcome mail | `account_created` | `…auth.account-created` |
| Booking created mail | `booking_created` | `…booking.booking-created` |
| Awaiting manual payment mail | `booking_pending_manual_payment` | `…booking.booking-pending-manual-payment` |
| Booking confirmed / ticketed mail | `booking_confirmed` | `…booking.booking-confirmed` |
| Booking failure mail | `booking_failed` | `…booking.booking-failed` |
| Booking cancelled mail | `booking_cancelled` | `…booking.booking-cancelled` |
| Booking amended mail | `booking_updated` | `…booking.booking-updated` |
| Booking expiry reminder job | `booking_expiring` | `…booking.booking-expiring` |
| Manual payment proof received | `manual_payment_received` | `…payment.manual-payment-received` |
| Payment success mail | `payment_success` | `…payment.payment-success` |
| Payment failure mail | `payment_failed` | `…payment.payment-failed` |
| Invoice mail | `invoice` | `…payment.invoice` |
| Refund requested mail | `refund_requested` | `…payment.refund-requested` |
| Refund status mail | `refund_updated` | `…payment.refund-updated` |
| Support ticket created mail | `support_ticket_created` | `…support.support-ticket-created` |
| Support reply mail | `support_reply` | `…support.support-reply` |
| Generic notification mail | `notification` | `…generic.notification` |

### Rules

1. Detect the client slug from booking → client profile → request/session/user/route.
2. Only when `client_slug === 'jetpk'`, swap the view via `JetpkEmailViewResolver::resolve()`.
3. Otherwise keep existing Master/default behaviour, byte-for-byte.
4. Never hardcode JetPK into a shared mail class without the client condition.
5. Never point Master at JetPK views.
6. `MAIL_FROM_ADDRESS` stays as configured. Only `MAIL_FROM_NAME` / visible sender display may show JetPakistan.

### Suggested guard

```php
$slug = $this->resolveClientSlug($booking ?? $user ?? null);

if ($slug === JetpkEmailViewResolver::CLIENT_SLUG) {
    $view = JetpkEmailViewResolver::resolve('booking_created', $slug);
    if ($view) {
        return $this->view($view)->with($this->jetpkPayload());
    }
}

return $this->view('emails.master.booking-created')->with($legacyPayload); // untouched
```

---

## 2. QA checklist

### Step 1 — clear and rebuild views

```bash
php artisan view:clear
php artisan view:cache
```

### Step 2 — render previews

```bash
php artisan ota:jetpk-email-preview --type=otp
php artisan ota:jetpk-email-preview --type=booking_created
php artisan ota:jetpk-email-preview --type=payment_success
php artisan ota:jetpk-email-preview --type=invoice
php artisan ota:jetpk-email-preview --all      # all 20 types
```

Output: `storage/app/email-previews/jetpk/{type}.html`

### Step 3 — automated audit

```bash
php artisan ota:jetpk-email-template-audit
```

Accepted only on `fail_count=0` / `AUDIT PASSED`.

### Step 4 — manual grep on rendered HTML

```bash
cd storage/app/email-previews/jetpk
grep -l '{{' *.html            # expect: no matches
grep -l '}}' *.html            # expect: no matches
grep -il 'parwaaz' *.html      # expect: no matches
grep -il 'yoursdomain' *.html  # expect: no matches
grep -il 'yd travel' *.html    # expect: no matches
grep -il 'haseeb-master' *.html# expect: no matches
grep -l 'placeholder 123' *.html # expect: no matches
grep -o 'src="[^"]*"' *.html   # every src must be https:// (or none at all)
```

### Step 5 — visual checks

- [ ] Logo renders from JetPK branding URL, **or** the text mark "JetPakistan" appears (no broken image icon).
- [ ] Header shows no Master client text.
- [ ] Footer shows JetPK support email/phone, JetPK copyright, no "Powered by …".
- [ ] CTA button is orange, pill-shaped, full-width on a narrow screen.
- [ ] Card is 640px on desktop, 100% on mobile.
- [ ] Body text ≥15px; no grey legal text under 12px.
- [ ] OTP box shows the code large and legible; security note present.
- [ ] Invoice totals align and print cleanly.
- [ ] Missing values hide their row rather than printing a variable name.

### Step 6 — client matrix

Send each preview HTML through a rendering test (Litmus/Email on Acid, or paste into a draft):

- [ ] Gmail web · [ ] Gmail Android · [ ] Gmail iOS
- [ ] Apple Mail iOS · [ ] Apple Mail macOS
- [ ] Outlook web · [ ] Outlook desktop (VML button)
- [ ] Yahoo · [ ] Hotmail/Outlook.com
- [ ] Narrow mobile (320px)

### Step 7 — non-mutation confirmation

- [ ] No email was sent by preview or audit.
- [ ] No DB rows written.
- [ ] No supplier call made.
- [ ] Master email output unchanged (render one Master email before/after and diff).

---

## 3. SFTP upload list

Upload preserving paths, relative to the Laravel project root. **Nothing here overwrites a Master template.**

```
resources/views/emails/themes/jetpakistan/layouts/base.blade.php
resources/views/emails/themes/jetpakistan/partials/header.blade.php
resources/views/emails/themes/jetpakistan/partials/footer.blade.php
resources/views/emails/themes/jetpakistan/partials/button.blade.php
resources/views/emails/themes/jetpakistan/partials/info-row.blade.php
resources/views/emails/themes/jetpakistan/partials/alert-box.blade.php
resources/views/emails/themes/jetpakistan/partials/booking-summary.blade.php
resources/views/emails/themes/jetpakistan/partials/passenger-summary.blade.php
resources/views/emails/themes/jetpakistan/partials/payment-summary.blade.php
resources/views/emails/themes/jetpakistan/partials/flight-itinerary.blade.php
resources/views/emails/themes/jetpakistan/partials/support-card.blade.php
resources/views/emails/themes/jetpakistan/auth/otp.blade.php
resources/views/emails/themes/jetpakistan/auth/sign-in-success.blade.php
resources/views/emails/themes/jetpakistan/auth/password-reset.blade.php
resources/views/emails/themes/jetpakistan/auth/account-created.blade.php
resources/views/emails/themes/jetpakistan/booking/booking-created.blade.php
resources/views/emails/themes/jetpakistan/booking/booking-pending-manual-payment.blade.php
resources/views/emails/themes/jetpakistan/booking/booking-confirmed.blade.php
resources/views/emails/themes/jetpakistan/booking/booking-failed.blade.php
resources/views/emails/themes/jetpakistan/booking/booking-cancelled.blade.php
resources/views/emails/themes/jetpakistan/booking/booking-updated.blade.php
resources/views/emails/themes/jetpakistan/booking/booking-expiring.blade.php
resources/views/emails/themes/jetpakistan/payment/manual-payment-received.blade.php
resources/views/emails/themes/jetpakistan/payment/payment-success.blade.php
resources/views/emails/themes/jetpakistan/payment/payment-failed.blade.php
resources/views/emails/themes/jetpakistan/payment/invoice.blade.php
resources/views/emails/themes/jetpakistan/payment/refund-requested.blade.php
resources/views/emails/themes/jetpakistan/payment/refund-updated.blade.php
resources/views/emails/themes/jetpakistan/support/support-ticket-created.blade.php
resources/views/emails/themes/jetpakistan/support/support-reply.blade.php
resources/views/emails/themes/jetpakistan/generic/notification.blade.php

app/Support/Emails/JetpkEmailBrandingResolver.php
app/Support/Emails/JetpkEmailViewResolver.php
app/Support/Emails/JetpkEmailSampleData.php

app/Console/Commands/OtaJetpkEmailTemplatePreviewCommand.php
app/Console/Commands/OtaJetpkEmailTemplateAuditCommand.php

config/jetpk_email.php

docs/email/jetpk-email-template-contract.md
docs/email/jetpk-email-integration-and-qa.md
```

**Post-upload on the server:**

```bash
php artisan view:clear
php artisan config:clear
php artisan optimize:clear   # if config was cached
php artisan ota:jetpk-email-template-audit
```

Laravel 11+ auto-discovers commands in `app/Console/Commands`. On older versions, register both commands in `app/Console/Kernel.php` `$commands`.

Ensure `storage/app/email-previews/` is writable by the web/CLI user.

---

## 4. Rollback notes

This package is **additive**. No Master file was edited, deleted, or overwritten, so rollback is a clean delete.

### Full rollback

```bash
rm -rf resources/views/emails/themes/jetpakistan
rm -rf app/Support/Emails/JetpkEmailBrandingResolver.php \
       app/Support/Emails/JetpkEmailViewResolver.php \
       app/Support/Emails/JetpkEmailSampleData.php
rm -f  app/Console/Commands/OtaJetpkEmailTemplatePreviewCommand.php \
       app/Console/Commands/OtaJetpkEmailTemplateAuditCommand.php
rm -f  config/jetpk_email.php
rm -rf storage/app/email-previews/jetpk
rm -f  docs/email/jetpk-email-template-contract.md \
       docs/email/jetpk-email-integration-and-qa.md

php artisan view:clear
php artisan config:clear
php artisan optimize:clear
```

Master email behaviour is unaffected before, during, and after rollback, because no shared mail class references these views until Cursor wires the client-slug guard.

### Partial rollback (after Cursor has wired mail classes)

1. Revert only the client-slug guard blocks in the mail classes — the JetPK views can stay on disk harmlessly.
2. Or force the fallback without a code change:
   ```php
   // config/jetpk_email.php
   'views' => [],   // resolver returns null → guard falls through to Master
   ```
   Then `php artisan config:clear`.

`JetpkEmailViewResolver::resolve()` returns `null` for any non-`jetpk` slug or unmapped type, so a partially-reverted guard degrades to Master rather than erroring.

### Safety invariants

- Removing this package cannot break Master email, because Master views and mail classes were never touched.
- `config/jetpk_email.php` is read via `config()` with array fallbacks in both resolvers; deleting it degrades to safe JetPK constants rather than throwing.
- Preview/audit commands are read-only: no mail transport, no DB write, no supplier call.
