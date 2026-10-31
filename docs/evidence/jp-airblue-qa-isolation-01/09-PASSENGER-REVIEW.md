# Passenger + review — pre-AirBook closure

```
PASSENGER_VALIDATION=PASS
PAX_POST=/booking/passengers (JSON Accept)
PAX_HTTP=200
OFFER_ID=parent grouped offer + EF fare_option_key
DOCUMENT=national_id (domestic LHE-KHI)
TERMS_VERSION=jetpk-checkout-terms-2026-08-22
```

Review:

```
url=/booking/review
customer_total=23750
fare_option_key retained (grouped-offer-*)
provider=airblue
connection_id=23
SUPPLIER_PNR=absent
SUPPLIER_BOOKING_ATTEMPT_COUNT=0
```

```
AIRBLUE_PASSENGER_FLOW=PASS
AIRBLUE_REVIEW=PASS
BOOK_CREATE_CALLS=0
```
