# Phases 7–8 — Vendor response acceptance criteria

## Sufficient response (CONTRACT_COMPLETE)

Vendor must explicitly answer:

```
NATIONAL_ID_DOCUMENT_TYPE_VALUE=<explicit value>
```

And provide enough information to determine **without inference**:

```
NATIONAL_ID_FORMAT_RULE=
DOCUMENT_ISSUE_COUNTRY_BUSINESS_REQUIRED=
DOCUMENT_EXPIRY_BUSINESS_REQUIRED=
DOCUMENT_HOLDER_NATIONALITY_BUSINESS_REQUIRED=
DOCUMENT_REQUIRED_AT_AIRBOOK=
DOCUMENT_REQUIRED_BEFORE_TICKETING=
DOCUMENT_REQUIREMENT_SCOPE=
```

For existing-PNR repairability, require explicit confirmation of:

```
DOCUMENT_UPDATE_OPERATION_SUPPORTED=
```

If supported, also require:

```
DOCUMENT_UPDATE_OPERATION=
DOCUMENT_UPDATE_MODIFICATION_TYPE=
DOCUMENT_UPDATE_SAMPLE_AVAILABLE=
```

If a national-ID sample is needed to implement safely and not provided:

```
VENDOR_SAMPLE_REQUIRED
```

## Classification vocabulary (use exactly one)

| Class | Meaning |
|---|---|
| `CONTRACT_COMPLETE` | All required fields above are explicit; implementation may proceed |
| `CONTRACT_PARTIAL` | Some required fields present; remain in vendor clarification |
| `CONTRACT_AMBIGUOUS` | Answers conflict or cannot be mapped without guessing |
| `VENDOR_SAMPLE_REQUIRED` | Core answers exist but a sanitized sample is still required before coding |

Do **not** infer missing answers. Do **not** implement from an incomplete response.

## Next phase gate

```
IF CONTRACT_COMPLETE:
  NEXT_PHASE=JP-AIRBLUE-TICKET-REJECTION-FIX-02
ELSE:
  NEXT_PHASE=WAIT_FOR_VENDOR_DOCUMENT_CONTRACT_RESPONSE
  (remain in vendor clarification)
```

## Current state (pre-response)

```
NATIONAL_ID_DOCUMENT_TYPE_PROVEN=NO
DOCUMENT_CONTRACT_VERIFIED=NO
RESPONSE_CLASSIFICATION=PENDING
NEXT_PHASE=WAIT_FOR_VENDOR_DOCUMENT_CONTRACT_RESPONSE
```
