# JP-AIRBLUE-WSDL-READ-THEN-TICKETING

## Scope

Deploy merged PR #127 (`config/suppliers.php` only) from main
`2ade5d0c3e77917d95210967d109a1b01e85ab15`, verify runtime WSDL-certified TEST
Read SOAPAction, perform exactly one Read on synthetic Booking C, and — only if
Read synced — perform exactly one AirDemandTicket. Always restore Conn22.

## Deploy (PR #127)

| Key | Value |
| --- | --- |
| MAIN_SHA | `2ade5d0c3e77917d95210967d109a1b01e85ab15` |
| Deploy path | `/home/pkjetp/jetpk_app/config/suppliers.php` |
| Backup | `/home/pkjetp/backups/airblue-pr127-pre-20261024T193037Z` |
| PRE_SHA256 | `56071fccdee79e210bd1df323e22e23b3c01bedbc9f5a084cdff959fa000fc36` |
| MAIN_SHA256 / POST_SHA256 | `d5ad94279fcaf4482103132cc4b29810861ab194c5dc0d1bb3da1b21713f528b` |
| CONFIG_PARITY | PASS |
| CONFIG_CACHE_PRE | NO |
| CONFIG_REFRESH_REQUIRED | NO |
| CONFIG_REFRESH_ACTION | NONE |
| OPTIMIZE_CLEAR | SKIPPED |
| PR127_DEPLOYED | YES |

Deployed file only: `config/suppliers.php`. No tests/docs/WSDL scripts. No blind git pull.

## Runtime offline verification (pre-provider)

| Key | Value |
| --- | --- |
| RUNTIME_READ_SOAP_ACTION | `http://zapways.com/air/ota/2.0/Read` |
| RUNTIME_READ_ENDPOINT | `https://otatest4.zapways.com/v2.0/OTAAPI.asmx` |
| READ_XML_PNR_ONLY | YES |
| READ_XML_TYPE14_ABSENT | YES |
| READ_XML_INSTANCE_ABSENT | YES |

Classification of published PDF host vs runtime remains
`DOC_ERRATUM_RUNTIME_DIFFERS`.

## Booking C

| Key | Value |
| --- | --- |
| BOOKING_C_MATCH_COUNT | 1 |
| PNR_C_HASH | `94b2bd04b019` |
| connection | 22 |
| provider | airblue |
| payment verified | YES |
| supplier payable | 26000 |
| customer payable | 26500 |
| ticketed pre | NO |

No raw PNR/Instance printed.

## Phase results

### Read (exactly one)

| Key | Value |
| --- | --- |
| READ_CALLS | 1 |
| READ_SYNCED | YES |
| READ_REASON | synced |
| READ_HTTP_STATUS | 200 |
| READ_FAULT_CLASS | (empty) |
| READ_CORRELATION_ID_PRESENT | NO |
| READ_PROVIDER_PNR_MATCH | YES |
| READ_ITINERARY_MATCH | YES |
| READ_PASSENGER_MATCH | YES |
| READ_UNTICKETED | YES |

Outgoing SOAPAction matched WSDL: `http://zapways.com/air/ota/2.0/Read`.

### Ticketing (exactly one, gated on Read PASS)

| Key | Value |
| --- | --- |
| TICKETING_ENABLED_PRE | false |
| CONN22_BACKUP_CREATED | YES |
| TICKETING_ENABLED_TEST | YES |
| TICKETING_CALLS | 1 |
| TICKETING_SUCCESS | NO |
| TICKETING_STATUS | failed |
| TICKETING_ERROR_CODE | provider_error |
| PROVIDER_ERROR_CODE | 197 |
| PROVIDER_ERROR_TYPE | 1 |
| PROVIDER_ERROR_MESSAGE_SANITIZED | Tickets cannot be issued until seats are selected. Seat selection is only supported in OTA version 3.0, but you are currently using version 2.0. |
| PROVIDER_CORRELATION_ID_PRESENT | YES |
| TICKETING_CLASSIFICATION | SEAT_REQUIREMENT |
| POST_TICKET_READ | NOT_RUN |
| SECOND_TICKETING_PROVIDER_CALLS | 0 |

Ticket XML offline precheck: Amount `26000.00` PKR Cash; customer payable not used.

### Restore / isolation

| Key | Value |
| --- | --- |
| TICKETING_ENABLED_RESTORED | YES |
| TICKETING_ENABLED_NOW (post-verify) | false |
| NON_CONN22_CONNECTION_ROWS_CHANGED | 0 |
| TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS | 0 |
| AIRBOOK_CALLS / CANCEL_CALLS / REFUND_CALLS | 0 |

## Closure decision

- WSDL-aligned Read is unblocked on TEST (`otatest4` + SOAPAction `/Read`).
- AirDemandTicket rejected by provider for **seat selection required on OTA v3 only** while this certification path remains Zapways OTA **v2.0**.
- Refund runtime PR **not** created (ticketing did not succeed).
- Do **not** switch protocol to v3, alter payment/fare experimentally, create another PNR, or retry ticketing in this sequence.

## FINAL_STATUS

`TICKETING_PROVIDER_REJECTION` / `SEAT_REQUIREMENT`

## Artifacts

- `_raw-pr127-deploy.txt`
- `_raw-phase-wsdl-read-ticket.txt`
- `_deploy-pr127.sh`
- `_phase-wsdl-read-ticket.php`
- `_stage_hash.py`
- `_verify-conn22.php`
