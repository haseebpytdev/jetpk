# Passenger + review

QA channel reuses `BookingController` passengers/review.

`processReviewSubmit` hard-stops when `QaStaffAirBlueContext::isActive`:

> AirBlue QA isolation stops before supplier booking. AirBook is not allowed in this phase.

Booking meta records `qa_scope` / `qa_connection_id` when created via QA channel.

`BOOK_CREATE_CALLS` (supplier AirBook) must remain 0.
