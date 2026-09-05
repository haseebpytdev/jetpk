# JP-EMAIL-PROD-QA-02 / JP-DASHBOARD-PROFILE-BRANDING-01

## Branch
`phase/jp-email-prod-branding-02`

## Objective
Transactional email production QA architecture, canonical company branding, and dashboard profile avatars.

## Included
- Session `photoUrl`; header/dropdown/sidebar avatars
- Company Profile & Branding nav + logo/favicon via existing AgencyBrandingController
- Role-prefixed subjects; booking-reference fallbacks; Laravel 13 plain-text MIME
- Scoped QA recipient lock, snapshot SQLite, `jetpk:email-prod-qa`
- Email shell wrap CSS

## Excluded
Flight performance, Traveler router.push, repository hygiene, public logo mutation, Gmail inbox (ChatGPT).

## Tests
`phpunit tests/Unit/Emails/JetpkEmailProdQaHelpersTest.php` via `tests/bootstrap.php` (repo-safe prepend over `C:\Users\khadi\ota\vendor` junction). 7 passed.

## Status
Implementation committed for independent GitHub audit. Production send/visual/Gmail remain in 02R1 continuation.
