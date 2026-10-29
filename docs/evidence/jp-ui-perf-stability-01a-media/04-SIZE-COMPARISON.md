# Size comparison (local synthetic)

Local GD environment: WebP yes, AVIF no (encoder absent).

Synthetic gradient PNG fixtures (small by construction) still prove format selection and derivative generation:

| Category | Source dims | Source bytes | Best WebP | Best JPEG | Reduction vs source (WebP) |
|----------|-------------|--------------|-----------|-----------|----------------------------|
| hero | 1600x900 | 6,533 | 3,900 | 49,202 | 40.3% |
| card | 1200x900 | 5,262 | 1,380 | 14,572 | 73.8% |
| support | 1600x900 | 6,533 | 2,212 | 26,318 | 66.1% |

Notes:

- Synthetic sources are tiny gradients; JPEG can exceed PNG size for this class of image.
- Production homepage baseline (~29 MB mostly photographic PNG) is the real target. After deploy + `jetpk:homepage-media-optimize`, expect multi-megabyte → low-hundreds-KB class savings on hero/cards.
- Do **not** claim new production GTmetrix numbers until post-deploy measurement.

DOM/contract expectations after this PR:

```text
picture/srcset present when responsive metadata exists
sizes present on card/support consumers
hero priority/eager + preload links when hero_lcp present
below-fold cards lazy (loading=lazy, fetchpriority=low)
original giant URL remains as compatibility fallback field
```
