# Pre-AirBook gate

Source hard stop remains in `BookingController::processReviewSubmit`:

> AirBlue QA isolation stops before supplier booking. AirBook is not allowed in this phase.

Live JSON submit probe returned:

```
HTTP=422
status=review_blocked
BOOK_CREATE_CALLS=0
```

No supplier AirBook/ticket/cancel/refund/void/reissue observed.

```
AIRBLUE_PREBOOK_GATE=STOP
SUPPLIER_BOOKING_ATTEMPTS=0
SUPPLIER_PNR=absent
```

Note: JSON error envelope uses `review_blocked` rather than echoing the Blade error string; mutation path still does not reach supplier create while QA session is active / booking remains unsubmitted.
