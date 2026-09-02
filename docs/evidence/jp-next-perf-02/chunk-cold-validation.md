# JP-NEXT-PERF-02A — Cold Next chunk validation

## Method

New browser contexts against production build `AJ9bvi6_QyxDfAP2TgofV` during Groups / One Way / Return measurement runs. Resource entries for `/_next/static/chunks/*` inspected in Playwright sessions.

## Results

```
SLOW_NEXT_CHUNKS_COLD=NONE_CONSISTENT
NEXT_CHUNK_404=0
NEXT_CHUNK_RETRY=0
NEXT_CHUNK_LOAD_ERROR=0
CROSS_BUILD_CHUNK_MISMATCH=0
```

## Chunks >1000ms

No **consistent** chunk download >1000ms reproduced across cold contexts after deploy warmth. Occasional cold document TTFB clusters remain network/OLS bound, not chunk 404/mismatch.

Prior warm finding `SLOW_NEXT_CHUNKS=NONE_POSTDEPLOY_WARM` remains true; cold explicit pass adds no reproducible >1s chunk defect requiring fix.
