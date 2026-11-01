# Phase 12 — Booking 57 PNR document repairability (offline)

## AirBookModify surface in repo

`AirBlueOtaXmlBuilder::buildAirBookModifyRequest` implements only:

- ModificationType `1` — full refund
- ModificationType `5` — seat / ancillary SpecialReqDetails

Any other type throws `unsupported_modification_type`.

No traveler Document add/update, name correction, or SSR document-update builder/tests found for Zapways v3.

```
EXISTING_PNR_DOCUMENT_REPAIR_SUPPORTED_OFFLINE=NO
BOOKING57_REPAIRABILITY=NOT_SUPPORTED_IN_CURRENT_INTEGRATION
BOOKING57_REPAIR_OPERATION=ABSENT
BOOKING57_REQUIRES_FRESH_AIRBOOK=YES
```

Deploying a future builder fix does **not** retrofit Document onto booking 57’s existing supplier PNR. Fresh AirBook (separate authorized phase) required after DocType verification + mapping fix. No supplier repair executed this phase.
