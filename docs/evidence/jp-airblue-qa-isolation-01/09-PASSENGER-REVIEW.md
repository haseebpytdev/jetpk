# Passenger + review — staff browser UAT

```
PASSENGER_VALIDATION=PASS
PAX_POST=/laravel/booking/passengers?format=json
PAX_STATUS=accepted
PAX_NEXT=/booking/review
OFFER_ID=parent grouped offer + EF fare_option_key
DOCUMENT=national_id (domestic LHE-KHI)
```

Review JSON (`/laravel/booking/review?format=json`):

```
ok=true
booking_session.status=review
itinerary.fare_family=EF
pricing.total=23750
pricing.currency=PKR
booking_reference=null
SUPPLIER_PNR=absent
```

```
AIRBLUE_PASSENGER_FLOW=PASS
AIRBLUE_REVIEW=PASS
BOOKING_DRAFT_CREATED=YES (checkout session status=review)
SUPPLIER_BOOKING_ATTEMPT_COUNT=0
```
