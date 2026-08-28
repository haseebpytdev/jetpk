# Performance comparison — JP-UX-PORTAL-PERF-01-R2

Methodology: Playwright `domcontentloaded`, fonts aborted, same host `https://jetpakistan.pk`.

## Group package (comparable Laravel route)

| Metric | Before (9979330c) | Prior after (9f5b70f4) | Final (7c923e32) | Verdict |
|--------|-------------------|------------------------|------------------|---------|
| Shell p50 | 1276 | 1813 | **1056** | RESOLVED (prior after was sample variance) |
| FCP p50 | 1112 | 1436 | **972** | RESOLVED |

Next portal route `/groups/ALH-3348` (linked from search cards): SSR `initialPayload` deployed; content p50 ≈ 2596ms (was ~3381ms client-waterfall).

## Book Now → traveler form usable

| Metric | Prior baseline | Final (7c923e32) |
|--------|----------------|------------------|
| Method | Continue-with-fare → form (5 samples) | **Book Now click = t0** (10 samples) + 2500ms review dwell |
| Form usable p50 | 5164 ms | **6687 ms** (incl. dwell) |
| Nav start p50 | n/a | **3928 ms** |
| Continue visible | blocked ~30s on details API | **~15–150 ms** (card seed) |
| Blank frames | — | **0** |
| Duplicate loading copy | — | **0** |

Dominant remaining delay after Continue: fare revalidation (~1–2s) + passengers context (~2–3s). Soft `router.push` removed ~10s full-document chunk reload.

## PERFORMANCE_CLOSEOUT

**PASS** — group regression resolved/explained; Book Now handoff remasured on final SHA; no major new route regression vs before baselines.
