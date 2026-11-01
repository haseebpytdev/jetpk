# JP-EMAIL-RECOVERY-01 — MIME / plain text

```
HTML_PART_VALID=YES
TEXT_PART_VALID=YES
HTML_MIME=PASS
PLAIN_TEXT_MIME=PASS
CSS_LEAK_IN_TEXT_PART=0
HTML_TAG_LEAK_IN_TEXT_PART=0
```

## Mechanism

`JetpkOperationalEventMail` delivers:

- `htmlString` HTML body (canonical JetPK shell)
- `text` view `emails.themes.jetpakistan.plain-text` with stripped plain body

Plain body is derived via `strip_tags` / `html_entity_decode` after `<br>` normalization. Unit/feature assertions confirm no CSS/style blocks or HTML tags in the plain text string used for the text part.
