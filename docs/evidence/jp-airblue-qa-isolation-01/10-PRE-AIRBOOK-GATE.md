# Pre-AirBook gate

Source hard stop remains in `BookingController::processReviewSubmit` for AirBlue QA isolation.

Live JSON review submit this loop:

```
HTTP=422
BOOK_CREATE_CALLS=0
```

No supplier AirBook/ticket/cancel/refund/void/reissue observed.

```
AIRBLUE_PREBOOK_GATE=STOP
SUPPLIER_BOOKING_ATTEMPTS=0
SUPPLIER_PNR=absent
```

This loop ends before provider create. Next authorized mutation phase: `JP-AIRBLUE-TEST-AIRBOOK-01` only after readiness YES.
