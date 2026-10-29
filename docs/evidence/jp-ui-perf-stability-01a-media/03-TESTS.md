# Tests

## PHPUnit (targeted)

```text
php vendor/bin/phpunit \
  tests/Unit/Services/Homepage/JetpkResponsiveImageOptimizerTest.php \
  tests/Unit/Services/Homepage/JetpkHeroImageOptimizerTest.php \
  tests/Unit/Support/Homepage/JetpkHeroLcpPresenterTest.php \
  tests/Feature/Jetpk/JetpkHomepageMediaOptimizeCommandTest.php \
  tests/Feature/JetpkHomepageMediaTest.php
```

Result (local):

```text
tests=18 passed=18 assertions=91 failures=0 errors=0 skipped=0
```

Coverage highlights:

- card profile no-upscale + JPEG/WebP when supported
- hero optimizer BC facade still activates
- dry-run backfill writes=no
- JetPakistan profile scope refusal for non-jetpk
- upload stores `responsive_media` while retaining original URL
- existing homepage media suite remains green

AVIF: environment without `imageavif` degrades to WebP/JPEG; tests do not hard-fail.

## Frontend

```text
node --test frontend/lib/homepage-media-responsive.test.mjs
```

Maps snake_case responsive payload; missing metadata falls back safely.
