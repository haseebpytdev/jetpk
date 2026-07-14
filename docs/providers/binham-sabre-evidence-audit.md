# Binham Sabre — Source-Code Evidence Audit

**Reference root:** `C:\Users\khadi\ota\Binham\public_html\modules\flights\sabre\`  
**Method:** Line-cited PHP only. No OTA code. No assumptions.

**Automation values:** `FULLY_AUTOMATED` | `PARTIAL` | `MANUAL` | `NOT_IMPLEMENTED`

---

## 1. Search

| Field | Evidence |
|-------|----------|
| **File** | `search.php` |
| **Function** | Top-level script (no named function); shop execution via closure `$sabre_execute_shop_requests` |
| **Lines** | Payload build: 537–644; multi-city slice selection: 542–546, 620–627; HTTP POST: 380–436 (closure), 389 (`curl_init($context['shopEndpoint'])`); response parse: 743–807 |
| **Sabre endpoint** | `POST {base_endpoint}/v{4\|5}/offers/shop` — version at L539–540 (`$shopVersion = $isNdcEnabled ? '5' : '4'`), URL at L540 |
| **Request payload shape** | `{ OTA_AirLowFareSearchRQ: { DirectFlightsOnly, Version, POS.Source[].PseudoCityCode, OriginDestinationInformation[] (RPH, DepartureDateTime, OriginLocation, DestinationLocation, TPA_Extensions.SegmentType), TravelPreferences (CabinPref, TPA_Extensions.DataSources, …), TravelerInfoSummary (SeatsRequested, AirTravelerAvail[].PassengerTypeQuantity, PriceRequestInformation), TPA_Extensions.IntelliSellTransaction } }` — L567–608, L620–627 |
| **Response fields consumed** | `groupedItineraryResponse` (L743–748); `itineraryGroups[0].itineraries` (L749); `scheduleDescs`, `legDescs` (L763–766); `fareBrandDescs`, `fareComponentDescs`, `priceClassDescriptions` (L793–807); per-itinerary pricing/legs processed L1044+ |
| **Database writes** | `api_tokens` update/insert (L277–282) for auth token cache only; no booking row written during search |
| **Automation** | **FULLY_AUTOMATED** — live Sabre shop HTTP call; results returned in JSON response |

---

## 2. Revalidation (GDS checkout)

| Field | Evidence |
|-------|----------|
| **File** | `helper.php` |
| **Function** | `SABRE_REVALIDATE_GDS_CHECKOUT` |
| **Lines** | Function: L1258–1423; segment builder call: L1279; payload: L1287–1323; endpoint: L1325; HTTP: L1329–1347; response parse: L1360–1415 |
| **Sabre endpoint** | `POST {baseEndpoint}/v4/shop/flights/revalidate` — L1325 |
| **Request payload shape** | `{ OTA_AirLowFareSearchRQ: { Version: "4", POS, OriginDestinationInformation[] (from SABRE_BUILD_GDS_CHECKOUT_REVALIDATE_SEGMENTS), TravelPreferences (NDC Disable, ATPCO/LCC Enable), TravelerInfoSummary (SeatsRequested, PassengerTypeQuantity), TPA_Extensions.IntelliSellTransaction.RequestType.Name: "50ITINS" } }` — L1287–1322 |
| **Response fields consumed** | `groupedItineraryResponse.itineraryGroups[0].itineraries[0].pricingInformation[0]` (L1365–1366); fare via `SABRE_EXTRACT_TOTAL_FARE_FROM_PRICING_INFO` (L1376–1378); booking classes via `SABRE_EXTRACT_GDS_REVALIDATE_BOOKING_CODES` (L1402–1403); messages via `SABRE_EXTRACT_GDS_REVALIDATE_MESSAGES` (L1360) |
| **Database writes** | None in this function (returns array only) |
| **Automation** | **FULLY_AUTOMATED** — automated Sabre HTTP revalidate; pass/fail by price/class match |

**Related (import revalidation):** `SABRE_REVALIDATE_GDS_IMPORT_PREVIEW` L1580–1915; same endpoint L1815; DB write on apply path in import flow only.

---

## 3. Multi-city Search

| Field | Evidence |
|-------|----------|
| **File** | `search.php` |
| **Function** | Top-level script; slice assembly in account loop |
| **Lines** | Route normalization: L104–133; multi-city slice override: L542–546; `OriginDestinationInformation[]` per slice: L620–627; same shop endpoint/version: L539–540 |
| **Sabre endpoint** | `POST {base_endpoint}/v4/offers/shop` (GDS) or `/v5/offers/shop` (NDC/BOTH) — L540 |
| **Request payload shape** | Same as Search; when `$type === 'multicity' && count($multicity_routes) >= 2`, `$slices = $multicity_routes` (L542–543); each slice appended to `OriginDestinationInformation[]` with `RPH` (L620–627) |
| **Response fields consumed** | Same as Search (`groupedItineraryResponse`, etc.) — L743+ |
| **Database writes** | `api_tokens` only (L277–282) |
| **Automation** | **FULLY_AUTOMATED** — same shop flow with multiple O&D entries; no separate multi-city API |

---

## 4. Multi-city Revalidation

| Field | Evidence |
|-------|----------|
| **File** | `helper.php` |
| **Function** | `SABRE_BUILD_GDS_CHECKOUT_REVALIDATE_SEGMENTS` (segment grouping); `SABRE_REVALIDATE_GDS_CHECKOUT` (HTTP) |
| **Lines** | Grouping (>24h gap → new O&D): L1105–1145; revalidate call uses grouped `origin_destinations`: L1279–1300, L1325; import path in `SABRE_REVALIDATE_GDS_IMPORT_PREVIEW` L1628+ / endpoint L1815 |
| **Sabre endpoint** | `POST {baseEndpoint}/v4/shop/flights/revalidate` — L1325 (checkout); L1815 (import preview) |
| **Request payload shape** | Multiple `OriginDestinationInformation[]` entries when segment gap > 86400s (L1112–1124, L1135–1144); each with `TPA_Extensions.Flight[]` nodes; wrapped in `OTA_AirLowFareSearchRQ` (L1287–1322) |
| **Response fields consumed** | Same as Revalidation — L1365–1415 |
| **Database writes** | None in revalidate functions |
| **Automation** | **FULLY_AUTOMATED** — same revalidate endpoint; multi-O&D built in PHP before POST |

---

## 5. PNR Create

| Field | Evidence |
|-------|----------|
| **File** | `helper.php` (core); entry `actions/issue.php` |
| **Function** | `SABRE_CREATE_PNR`; HTTP route calls it at `actions/issue.php` L24 |
| **Lines** | Function: L5111–6098; GDS endpoint: L5892; NDC endpoint: L5666; GDS payload root `CreatePassengerNameRecordRQ`: L5827–5890 area; HTTP POST: L5906–5942; response parse GDS: L5963–5964; NDC: L5949–5961; DB update: L6054–6077 |
| **Sabre endpoint (GDS)** | `POST {base_endpoint}/v2.4.0/passenger/records?mode=create` — L5892 |
| **Sabre endpoint (NDC)** | `POST {base_endpoint}/v1/orders/create` — L5666 (preceded by mandatory `POST /v1/offers/price` L5669–5701) |
| **Request payload shape (GDS)** | `{ CreatePassengerNameRecordRQ: { TravelItineraryAddInfo, AirBook (FlightSegment[], HaltOnStatus, …), SpecialReqDetails, PostProcessing, AirPrice[] } }` — L5850–5890 |
| **Request payload shape (NDC)** | Order create object built L5655–5666; offer price `{ party, query[].offerItemId[] }` L5670–5688 |
| **Response fields consumed (GDS)** | `CreatePassengerNameRecordRS.ItineraryRef.ID` or `ItineraryRef.ID` (L5964); expiry via `SABRE_EXTRACT_TIME_LIMIT` (L5967) |
| **Response fields consumed (NDC)** | `order.pnrLocator`, `id`, `order.id`, `booking.id`, etc. (L5952–5955); expiry L5958 |
| **Database writes** | `bookings` UPDATE: `pnr`, `booking_ref`, `supplier_account_id`, `booking_response`, `booking_status` => `'confirmed'`, `updated_at`, optional `expiry_date`, optional `booking_data` — L6054–6077 |
| **Automation** | **FULLY_AUTOMATED** — live Sabre PNR/order create; `issue.php` comment L8: "Issue a PNR via Sabre CreatePassengerNameRecord API" |

**Note:** Binham `issue` = PNR creation, not ticket issuance.

---

## 6. Ticket Issue

| Field | Evidence |
|-------|----------|
| **File** | `actions/issue.php` → `SABRE_CREATE_PNR` only |
| **Function** | Route handler L12–42; calls `SABRE_CREATE_PNR` L24 |
| **Lines** | `actions/issue.php` L1–42; Sabre module grep for `air/ticket`, `AirTicket`, `ticket/documents`, `voidFlightTickets`, `refundFlightTickets`: **no matches** in `modules/flights/sabre/` |
| **Sabre endpoint** | **None for ticketing** |

### TICKET ISSUE NOT PRESENT IN BINHAM

No Sabre ticketing endpoint (e.g. `/v1.3.0/air/ticket`) exists in the Binham Sabre module. `flights/sabre/issue` creates a PNR only.

| **Automation** | **NOT_IMPLEMENTED** (for ticket issuance) |

---

## 7. Ticket Retrieve

| Field | Evidence |
|-------|----------|
| **File** | `helper.php` |
| **Function** | `SABRE_GET_BOOKING_STATUS` |
| **Lines** | Function: L6269–6764; getBooking: L6381–6424; NDC fallback retrieve: L6436–6448; `isTicketed` parse: L6546–6552; status derivation: L6699–6712; DB update: L6723–6730 |
| **Sabre endpoint (primary)** | `POST {base_endpoint}/v1/trip/orders/getBooking` — L6381; payload `{ confirmationId: pnr }` L6382 |
| **Sabre endpoint (NDC fallback)** | `POST {base_endpoint}/v1/ndc/orders/retrieve` — L6436; payload `{ orderId: booking_ref }` L6437 |
| **Request payload shape** | getBooking: `{ confirmationId: string }` L6382; NDC: `{ orderId: string }` L6437 |
| **Response fields consumed** | `isTicketed`, `order.isTicketed`, `booking.isTicketed` (L6546–6551); `order.status`, `orders[0].status`, `booking.status`, top `status` (L6544–6545); `flights` / `itinerary.flights` / `allSegments` segment statuses (L6570–6577); remarks via `SABRE_EXTRACT_AIRLINE_REMARKS` / `SABRE_STORE_AIRLINE_REMARKS` (L6531–6534) |
| **Database writes** | `bookings` UPDATE: `booking_status`, `updated_at`, optional `expiry_date` — L6728–6730; remarks stored via `SABRE_STORE_AIRLINE_REMARKS` (L6534) |
| **Automation** | **PARTIAL** — automated status/ticketing flag from getBooking; no dedicated ticket-document or ticket-number retrieve API in module; refund reads `ticketNumber` from stored `booking_response` only (`actions/refund.php` L138–153) |

---

## 8. Void

| Field | Evidence |
|-------|----------|
| **File** | `actions/void.php` |
| **Function** | Route handler `$router->post('flights/sabre/void', …)` |
| **Lines** | Endpoint: L210; payload: L212–214; HTTP DELETE: L217–224; DB update: L252–257 |
| **Sabre endpoint** | `DELETE {baseUrl}/v1/trip/orders/cancelBooking` — L210 |

### VOID IS CANCELBOOKING

Void uses the same cancelBooking API as cancel — not a dedicated void-ticket endpoint.

| **Request payload shape** | `{ confirmationId: booking.pnr }` — L212–214 |
| **Response fields consumed** | Full JSON decoded to `$cancelData` (L232); stored in DB; HTTP status checked L235–244 |
| **Database writes** | `bookings` UPDATE: `booking_status` => `'cancelled'`, `void_response` => JSON — L252–257 |
| **Automation** | **PARTIAL** — automated cancelBooking call; not airline void-ticket API |

---

## 9. Refund

| Field | Evidence |
|-------|----------|
| **File** | `actions/refund.php` |
| **Function** | Route handler `$router->post('flights/sabre/refund', …)` |
| **Lines** | Header comment L6–31; ticket extract from DB: L138–153; refund record: L161–173; DB update: L182–187 |
| **Sabre endpoint** | **None** — no `curl_init` / HTTP call to Sabre in this file |

### REFUND IS MANUAL

| **Request payload shape** | POST params: `invoice_id`, optional `refund_amount`, `refund_reason` (L17–19 comment) |
| **Response fields consumed** | Reads `booking_response` JSON from DB for `passengers[].ticketNumber` or `ticketNumber` (L141–153); no live Sabre refund response |
| **Database writes** | `bookings` UPDATE: `booking_status` => `'refund_pending'`, `refund_response` => JSON — L182–187; on error: `error_response`, `booking_status` => `'refund_failed'` (L231–234) |
| **Automation** | **MANUAL** — L197 message: "Manual processing required through Sabre Red Workspace"; L171–172 note documents manual Red Workspace workflow |

---

## 10. Cancel

| Field | Evidence |
|-------|----------|
| **File** | `actions/cancel.php` |
| **Function** | Route handler `$router->post('flights/sabre/cancel', …)` |
| **Lines** | Endpoint: L203; payload: L205–207; HTTP DELETE: L210–217; DB update: L250–255 |
| **Sabre endpoint** | `DELETE {baseUrl}/v1/trip/orders/cancelBooking` — L203 |
| **Request payload shape** | `{ confirmationId: booking.pnr }` — L205–207 |
| **Response fields consumed** | `$cancelData` from JSON (L225); HTTP error fields `errorCode`, `message`, `error` (L231–234); ticketed flag from prior booking data L189 |
| **Database writes** | `bookings` UPDATE: `booking_status` => `'cancelled'`, `cancel_response` => JSON — L250–255; on error: `error_response`, `booking_status` => `'cancel_failed'` (L291–294) |
| **Automation** | **FULLY_AUTOMATED** — live cancelBooking DELETE; success updates DB |

---

## 11. NDC Reprice

| Field | Evidence |
|-------|----------|
| **File** | `helper.php` |
| **Function** | `SABRE_REPRICE_NDC_IMPORT_PREVIEW`; parser `SABRE_EXTRACT_NDC_REPRICE_OFFER` |
| **Lines** | Reprice HTTP: L3460–3481; payload L3460; offer extract L3515; parser L3315–3374+; apply path also in `SABRE_RECHECK_IMPORTED_NDC_BOOKING_PRICE` L3595+ |
| **Sabre endpoint** | `POST {config.base_endpoint}/v1/offers/repriceOrder` — L3461 |
| **Request payload shape** | `{ request: { orderId: string } }` — L3460 |
| **Response fields consumed** | `warnings[]`, `errors[]` (L3493–3503); offers via `response.offers` or `offers` (L3316–3317); `offer.price.totalAmount`, taxes, `offerItems[]`, `offerItemId`, passenger refs (L3331–3374) |
| **Database writes** | None in reprice function itself; preview/`price_revalidation` array updated in memory; persisted on import via `SABRE_IMPORT_GDS_BOOKING` insert L3057+ or recheck apply L3813 area |
| **Automation** | **FULLY_AUTOMATED** — live repriceOrder HTTP |

---

## 12. NDC Order Change

| Field | Evidence |
|-------|----------|
| **File** | `helper.php` |
| **Function** | `SABRE_IMPORT_GDS_BOOKING` (on import when reprice changed); `SABRE_RECHECK_IMPORTED_NDC_BOOKING_PRICE` (when `$apply` and price changed) |
| **Lines** | Import path: L2925–3017 (endpoint L2963); recheck apply path: L3728–3759 (endpoint L3744) |
| **Sabre endpoint** | `POST {base_endpoint}/v1/orders/change` — L2963, L3744 |
| **Request payload shape** | `{ id: orderId, orderItemUpdates: [{ acceptOffers: [{ offerId, selectedOfferItems }] }] }` — L2954–2961, L3735–3742 |
| **Response fields consumed** | HTTP 200 check; `errors[]` (L2987–2998); airline remarks via `SABRE_EXTRACT_AIRLINE_REMARKS` (L3001–3003); full `$changeData` stored in `preview.raw_response` (L3008) |
| **Database writes** | Import: `bookings` INSERT via `SABRE_IMPORT_GDS_BOOKING` L3057–3099+; recheck apply: `bookings` UPDATE L3813 (price/booking_data path in recheck function) |
| **Automation** | **FULLY_AUTOMATED** — live orders/change when reprice delta requires accept-offer |

---

## 13. NDC Retrieve

| Field | Evidence |
|-------|----------|
| **File** | `helper.php` |
| **Function** | `SABRE_FIND_NDC_BOOKING_BY_REFERENCE`; `SABRE_FETCH_NDC_AIRLINE_REMARKS`; `SABRE_GET_BOOKING_STATUS` (NDC fallback) |
| **Lines** | Find by reference / orders/view: L4287–4413 (endpoint L4350); fetch remarks: L5003–5101 (`/v1/orders/view` L5080, `/v1/ndc/orders/retrieve` L5086); status NDC fallback: L6436–6448 |
| **Sabre endpoint** | `POST {base_endpoint}/v1/orders/view` — L4350, L5080; payload `{ id: reference }` L4347 |
| **Sabre endpoint (alternate)** | `POST {base_endpoint}/v1/ndc/orders/retrieve` — L5086, L6436; payload `{ orderId }` L5086, L6437 |
| **Request payload shape** | orders/view: `{ id: string }` L4347; ndc retrieve: `{ orderId: string }` L5086, L6437 |
| **Response fields consumed** | orders/view: `travelers`/`passengers`, `order.pnrLocator`, `order.id`, `flights`, `errors` (L4386–4418); retrieve: remarks and status signals via extractors L5088–5089; getBooking path: same as Ticket Retrieve |
| **Database writes** | Find/import: new `bookings` row on import (downstream); status check: L6728–6730; remarks: `SABRE_STORE_AIRLINE_REMARKS` L6534 |
| **Automation** | **FULLY_AUTOMATED** — live NDC retrieve/view HTTP |

---

## Grep summary (Sabre module)

Searched `Binham/public_html/modules/flights/sabre/` for:

- `air/ticket`, `AirTicket`, `ticket/documents`, `voidFlightTickets`, `refundFlightTickets`

**Result:** No matches.

---

## Capability matrix (Binham only)

| Capability | Automation |
|------------|------------|
| Search | FULLY_AUTOMATED |
| Revalidation | FULLY_AUTOMATED |
| Multi-city Search | FULLY_AUTOMATED |
| Multi-city Revalidation | FULLY_AUTOMATED |
| PNR Create | FULLY_AUTOMATED |
| Ticket Issue | NOT_IMPLEMENTED |
| Ticket Retrieve | PARTIAL |
| Void | PARTIAL (cancelBooking) |
| Refund | MANUAL |
| Cancel | FULLY_AUTOMATED |
| NDC Reprice | FULLY_AUTOMATED |
| NDC Order Change | FULLY_AUTOMATED |
| NDC Retrieve | FULLY_AUTOMATED |

---

*Generated from Binham reference PHP source. No OTA implementation claims.*
