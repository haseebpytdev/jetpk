# Zapways OTA v2.06 AirBook — Document vs JetPakistan Audit

**MAIN_SHA:** `d1675678b0d3d485b23d4bcbfbd4a848fe34c3f8`  
**Branch:** `fix/airblue-zapways-airbook-doc-contract`  
**Scope:** Conn 22 TEST / otatest4 only

## Contract matrix (pre-fix → post-fix)

| DOCUMENTED_FIELD | DOCUMENTED_LOCATION | CURRENT_JETPK (before) | MATCH | DIFFERENCE | RECOMMENDED_CHANGE |
|---|---|---|---|---|---|
| OriginDestinationOption | AirBook ← LowFareSearch | Reconstructed FlightSegments only | NO | Lost StopQuantity, Status, Equipment, exact ODO grouping | Preserve search ODO XML fragment |
| FlightSegment attrs | LowFareSearch RS | Partial (RBD, times, carriers) | PARTIAL | Status/StopQuantity/Equipment often dropped | Carry forward fragment |
| PTC_FareBreakdown | AirBook PriceInfo | Recomputed amounts + nested FareBasisCodes | PARTIAL | Tax children / FareInfo attrs lost | Preserve AirItineraryPricingInfo children as PriceInfo |
| FareInfo/@FareBasisCode | PTC_FareBreakdown | Parsed only FareBasisCodes/text | NO | Attribute ignored; RBD fallback common | Parse attr first |
| NameTitle | PersonName | NamePrefix | NO | Wrong element name per v2.06 | Emit NameTitle |
| PassengerTypeQuantity | AirTraveler child | PassengerTypeCode attribute | NO | Attribute vs documented child | Emit PassengerTypeQuantity child |
| Telephone attrs | AirTraveler | PhoneNumber only | NO | Missing PhoneLocationType, CountryAccessCode; v2.06 defines 10=Mobile | Full telephone attrs; default mobile type 10 |
| Document | AirTraveler | DocID/Type/Issue/Expire/Nationality | YES | Aligned | Keep |
| Gender | AirTraveler | Male/Female | YES | After PR113 | Keep |
| RuleInfo | Pricing | N/A (not sent) | YES | Docs say omit OK | Strip if present in preserved XML |

## Audit answers

```
DOCUMENT_AUDIT_COMPLETE=YES
SEARCH_ODO_CONTINUITY_MATCH=NO→FIXED (preserve fragment)
FARE_BASIS_PARSER_MATCH=NO→FIXED (FareInfo/@FareBasisCode priority)
PTC_FARE_BREAKDOWN_MATCH=NO→FIXED (preserve pricing fragment)
NAME_TITLE_MATCH=NO→FIXED
PASSENGER_TYPE_STRUCTURE_MATCH=NO→FIXED
TELEPHONE_STRUCTURE_MATCH=NO→FIXED
DOCUMENT_STRUCTURE_MATCH=YES
```


## Review hardening

```
ROUND_TRIP_WRAPPER_COMPOSITION=FIXED_OFFLINE
ROUND_TRIP_SUPPLIER_CERTIFICATION=NOT_YET_RUN
PHONE_LOCATION_TYPE_MOBILE=10
CHECKOUT_PROVIDER_CONTEXT_AUTHORITY=SERVER_SIDE_SEARCH_STORE
ADDITIONAL_PROVIDER_CALLS_AFTER_ONE_WAY_PASS=0
```
