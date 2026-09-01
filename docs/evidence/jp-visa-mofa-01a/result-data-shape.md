# Result data shape

## Status

**Not observed.** No authorized successful lookup in 01A.

| Flag | Value |
|---|---|
| RESULT_STRUCTURED_DATA_AVAILABLE | **PENDING_AUTHORIZED_SAMPLE** |
| MOFA_RESULT_FIELD_NAMES | `PENDING_AUTHORIZED_SAMPLE` |

## Method for later closure

On authorized success, record **field names only** (never values) from HTML/result UI categories such as:

- status
- visa number
- application number
- name
- nationality
- visa type
- validity / duration / entries
- issue / expiry date

If only PDF exists with no structured HTML fields: set `RESULT_STRUCTURED_DATA_AVAILABLE=NO` accurately.
