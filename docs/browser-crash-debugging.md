# Browser Crash Debugging Checklist

Use this checklist when a Chromium/Opera tab crashes while testing the OTA public flow.

## Autocomplete API sanity

1. Run `GET /airports/search?q=lh`.
2. Confirm the response is compact JSON.
3. Confirm result count is `<= 15`.

## Server-side safety checks

1. Check `storage/logs/laravel.log` for any warnings/errors during the crash window.
2. Confirm no public view preloads all airports in HTML or inline JavaScript.

## Browser-side safety checks

1. Open DevTools Console and watch for repeated script errors.
2. Open DevTools Network and verify autocomplete requests are throttled and not unbounded.
3. Re-test in Chrome Incognito to rule out Opera/extension conflicts.

