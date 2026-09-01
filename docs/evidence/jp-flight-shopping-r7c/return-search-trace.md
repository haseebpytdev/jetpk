# Return search trace

## Architecture

Return Paired View uses supplier-returned round-trip offers (single search), not separate outbound+inbound supplier calls for SUPPLIER_RETURNED pairing.

## Root cause (hang)

`use-flight-results` view/filter refresh called `loadPage(..., "refresh")` which bumped `requestSeq` and aborted polls, but did **not** call `schedulePoll` when status remained searching. UI stayed on skeletons indefinitely.

Secondary: URL sync could drop `search_id` via `history.replaceState` / `router.replace`.

## Fix

- Restart `schedulePoll` after refresh when `shouldPoll`
- Preserve `search_id` in URL sync
- Client deadline 60s (empty) / 90s settle with truthful timeout UI

## Live timings (ISB–DXB RT pair)

| Metric | Sample |
|---|---|
| First usable pairs | ~7.1s (early sample) |
| Pair ready | ~22–25s (later samples) |
| Over 30s hang with infinite skeleton | 0 after fix |

## Trace IDs (sanitized)

| Key | Value |
|---|---|
| ONEWAY_TRACE (search_id) | `6d4b2da6-efee-4a40-9687-eea164fde52e` |
| RETURN_EDIT / RT search_id | `7926faca-06ce-4437-9c70-0ebc19627b9c` |
| RETURN_OUTBOUND_REQUEST_STARTED | YES (single RT search) |
| RETURN_OUTBOUND_REQUEST_COMPLETED | YES |
| RETURN_INBOUND_REQUEST_STARTED | N/A (supplier-returned RT) |
| RETURN_PAIRING_COMPLETED | YES |
| RETURN_CLIENT_COMPLETION_EVENT | paired cards rendered |
| RETURN_TIMEOUT_SOURCE | client 60s/90s when empty |
