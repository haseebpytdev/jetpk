# JetPakistan Canonical Responsive UI + CMS Correction Runtime Manifest

Baseline SHA:
5f97dc6c512d59ac2106c2bfa9854da2dc1210c8

Merge commit:
1a64a0ce017cc77400c90025adadd6d1a8e79f65

Runtime source SHA:
a9bc7826f4beb14deeaef29e17bbf4bfd195a737

Runbook content SHA:
725d6a470393ac45b4dfbbff6ca65a06c90214e5

Runbook reference SHA:
ae880169ed331de2aaff2a40f68afc1ef5b9999a

Runtime uploads:
50

Upload-target presence checks:
50

Git runtime deletions:
98

public_html mirror deletions:
5

Total explicit rm -f commands:
103

Modified or added public assets:
0

Deleted public assets:
5

Public mirrored uploads:
0

## Why 103 SSH removals for 98 Git deletions

Git records 98 deleted runtime paths under `jetpk_app`. Five deleted Laravel public assets must also be removed from the independent `/home/pkjetp/public_html` mirror:

- `public/css/ota-mobile-app.css`
- `public/css/v2/ota-mobile-app-v2.css`
- `public/js/ota-mobile-app.js`
- `public/js/v2/ota-mobile-app-v2.js`
- `public/themes/mobile/jetpakistan-app/css/app.css`

That yields 98 + 5 = 103 explicit `rm -f` commands.

## Verification gate (local, pre-deploy)

- Playwright: 36/36 passed
- PHP: 102/102 passed
- PHP assertions: 534
- route health: fail=0
- server_errors=0
- canonical email audit: fail_count=0
- homepage content audit: fail_count=0
- homepage media audit: fail_count=0
- customization audit: fail=0

## Related documents

- SFTP: `docs/JETPK_CANONICAL_UI_CMS_CORRECTION_SFTP_COMMANDS.txt`
- SSH: `docs/JETPK_CANONICAL_UI_CMS_CORRECTION_SSH_COMMANDS.md`
- Rollback: `docs/JETPK_CANONICAL_UI_CMS_CORRECTION_ROLLBACK.md`
- Diff: `docs/JETPK_CANONICAL_UI_CMS_CORRECTION_DIFF.tsv`
- Deletion review: `docs/JETPK_CANONICAL_UI_DELETION_SAFETY_REVIEW.md`
- Visual acceptance: `docs/JETPK_CANONICAL_UI_VISUAL_ACCEPTANCE.md`

## Prohibited during deployment

No `php artisan migrate`, no database seeding, no CMS reset/default/restore/publish, no supplier/booking/ticketing/PNR/cancellation mutations.
