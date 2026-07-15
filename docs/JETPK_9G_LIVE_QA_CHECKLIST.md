# JetPK 9G Live QA Checklist

## Admin
- [ ] Dashboard loads JetPK shell (`jp-dash`), KPIs visible
- [ ] Bookings list/detail styled, no Tabler leak
- [ ] **Suppliers → Create** — structured sections, sticky save, no colliding fields
- [ ] **Suppliers → Edit** — diagnostics card + form sections
- [ ] Page settings → Home — all sections editable, preview works
- [ ] Page settings → Footer / Global
- [ ] Branding settings save

## Staff
- [ ] Dashboard + bookings same quality as admin shell

## Agent
- [ ] Portal layout, bookings, no admin actions

## Customer
- [ ] Account dashboard, bookings, profile

## DevCP
- [ ] Neutral owner branding only
- [ ] No JetPK orange in DevCP chrome

## Page settings
- [ ] Publish home draft → public homepage reflects why-book section
- [ ] Asset upload hero_background

## Email previews
```bash
php artisan ota:jetpk-email-preview otp
php artisan ota:jetpk-email-preview email_verification
php artisan ota:jetpk-email-template-audit
```
- [ ] No Parwaaz/YD/Master in rendered HTML
- [ ] Links use `https://jetpakistan.pk`

## No-leak verification
```bash
php artisan ota:jetpk-flow-leak-audit
php artisan jetpk:master-trace-audit
```
