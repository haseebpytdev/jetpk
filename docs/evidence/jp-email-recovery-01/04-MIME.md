# JP-EMAIL-RECOVERY-01 — MIME / plain text

```
HTML_PART_VALID=YES
TEXT_PART_VALID=YES
HTML_MIME=PASS
PLAIN_TEXT_MIME=PASS
PLAIN_TEXT_RENDERER_SHARED=YES
STYLE_BLOCK_CONTENT_REMOVED=YES
CSS_LEAK_IN_TEXT_PART=0
HTML_TAG_LEAK_IN_TEXT_PART=0
```

## Mechanism

`JetpkOperationalEventMail` delivers:

- `htmlString` HTML body (canonical JetPK shell)
- `text` view `emails.themes.jetpakistan.plain-text` with stripped plain body

Plain body is derived via shared `JetpkEmailPlainTextRenderer::fromHtml()` used by:

- `OtaNotificationService` canonical rendering
- `BookingCommunicationService::canonicalizeCustomerMailable`
- `JetpkOperationalEmailService::plainBodyFromHtml`

The helper removes complete `<style>`, `<script>`, `<head>`, comment / conditional blocks **before** `strip_tags`, preserves readable breaks for `br` / paragraphs / headings / table cells, decodes entities, and normalizes whitespace. Unit/feature assertions render the real JetPK `layouts.base` style block and require zero CSS/HTML tag leak in text/plain.
