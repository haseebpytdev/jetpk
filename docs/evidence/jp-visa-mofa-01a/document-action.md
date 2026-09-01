# Document action

## Observed user-visible outcome

Successful inquire redirects to the official printable Umrah visa HTML page:

`/Home/PrintedUmrahVisa`

This matches the owner clue path class (`PrintedUmrahVisa`), now **live-proven**.

## Action model

| Key | Value |
|---|---|
| Primary document surface | HTML visa print layout |
| Separate on-page “Download PDF” API | **Not observed** |
| Print support | `print.css` present; browser print / Save-as-PDF is client-side |

## Implication for JetPakistan

Prefer:

1. Structured field summary from HTML (names above)
2. Session-bound relay/stream of **official HTML document bytes**, or open/print guidance
3. Optional **local** PDF/PNG derived from official document — labeled as image/PDF **copy**, not a MOFA-issued PDF file
