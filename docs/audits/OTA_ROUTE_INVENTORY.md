# OTA Route Inventory

Generated: 2026-06-17T10:56:25+00:00

Command: `php artisan ota:audit-routes --export=docs/audits/OTA_ROUTE_INVENTORY.md`

Verify live routes: `php artisan route:list`

## Summary

| Metric | Count |
|--------|------:|
| Total routes | 410 |
| Auth middleware | 325 |
| Mutating (POST/PATCH/PUT/DELETE) | 193 |
| Mutating without auth (heuristic) | 16 |

## Buckets

| Bucket | Count |
|--------|------:|
| public | 50 |
| auth | 31 |
| admin | 199 |
| staff | 42 |
| agent | 47 |
| customer | 19 |
| dev_cp | 21 |
| api | 0 |
| health | 1 |
| unclassified | 0 |

## Bucket details

### public (50)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| GET | `/` | `home` | web | — |
| POST | `view-preference/mobile` | `view-preference.mobile` | web | — |
| POST | `view-preference/desktop` | `view-preference.desktop` | web | — |
| GET | `mobile-view` | `view-preference.mobile-get` | web | — |
| GET | `mobile-app-preview` | `view-preference.mobile-preview` | web | — |
| GET | `desktop-view` | `view-preference.desktop-preview` | web | — |
| GET | `request-demo` | `request-demo` | web | — |
| GET | `support` | `support` | web, platform.module:support_system | support_system |
| POST | `support` | `support.store` | web, platform.module:support_system, throttle:10,1 | support_system |
| GET | `support/submitted` | `support.submitted` | web, platform.module:support_system | support_system |
| GET | `about-us` | `about` | web | — |
| GET | `pages/{slug}` | `pages.show` | web | — |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `contact` | `-` | web | — |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `agent-network` | `agent-network` | web | — |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `register/customer` | `-` | web | — |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `register/agent` | `-` | web | — |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `flights/search` | `flights.search` | web | — |
| GET | `flights/results` | `flights.results` | web, platform.module:public_flight_search | public_flight_search |
| GET | `flights/results/search` | `flights.results.search` | web, platform.module:public_flight_search, throttle:public-flight-results-search | public_flight_search |
| GET | `flights/results/data` | `flights.results.data` | web, platform.module:public_flight_search, throttle:public-flight-results-data | public_flight_search |
| POST | `flights/results/revalidate-offer` | `flights.results.revalidate-offer` | web, platform.module:public_flight_search, throttle:public-flight-results-data | public_flight_search |
| GET | `flights/return-options` | `flights.return-options` | web, platform.module:public_flight_search | public_flight_search |
| GET | `flights/return-options/data` | `flights.return-options.data` | web, platform.module:public_flight_search, throttle:public-flight-results-data | public_flight_search |
| POST | `flights/select-return-combo` | `flights.select-return-combo` | web, platform.module:public_flight_search | public_flight_search |
| GET | `flights/results/offer` | `flights.results.offer` | web, platform.module:public_flight_search | public_flight_search |
| GET | `flights/details/{id}` | `flights.details` | web, platform.module:public_flight_search | public_flight_search |
| GET | `airports/search` | `airports.search` | web, throttle:60,1 | — |
| GET | `groups/search` | `group-ticketing.search` | web, platform.module:public_umrah_groups | public_umrah_groups |
| GET | `groups/search/results` | `group-ticketing.search.results` | web, platform.module:public_umrah_groups | public_umrah_groups |
| GET | `groups/facets` | `group-ticketing.facets` | web, platform.module:public_umrah_groups | public_umrah_groups |
| GET | `groups/package/{inventory}` | `group-ticketing.show` | web, platform.module:public_umrah_groups | public_umrah_groups |
| GET | `umrah-groups` | `umrah-groups.index` | web, platform.module:public_umrah_groups | public_umrah_groups |
| GET | `umrah-groups/{package}` | `umrah-groups.show` | web, platform.module:public_umrah_groups | public_umrah_groups |
| GET|POST | `booking/passengers` | `booking.passengers` | web, platform.module:customer_checkout, throttle:public-booking-submit | customer_checkout |
| GET|POST | `booking/review` | `booking.review` | web, platform.module:customer_checkout, throttle:public-booking-submit | customer_checkout |
| POST | `booking/{booking}/accept-updated-fare` | `booking.accept-updated-fare` | web, platform.module:customer_checkout, throttle:public-booking-submit | customer_checkout |
| POST | `booking/{booking}/decline-updated-fare` | `booking.decline-updated-fare` | web, platform.module:customer_checkout, throttle:public-booking-submit | customer_checkout |
| GET | `booking/confirmation` | `booking.confirmation` | web | — |
| GET | `lookup-booking` | `booking.lookup` | web, platform.module:customer_booking_lookup | customer_booking_lookup |
| POST | `lookup-booking` | `lookup-booking.submit` | web, platform.module:customer_booking_lookup, throttle:lookup-booking | customer_booking_lookup |
| GET | `guest/bookings/{booking}/access/{token}` | `guest.bookings.show` | web, platform.module:customer_booking_lookup | customer_booking_lookup |
| GET | `guest/documents/{bookingDocument}/download` | `guest.documents.download` | web, platform.module:customer_booking_lookup | customer_booking_lookup |
| POST | `guest/bookings/{booking}/access/{token}/payment-proof` | `guest.bookings.payment-proof` | web, platform.module:payment_proofs, throttle:payment-proof-submit | payment_proofs |
| POST | `guest/bookings/{booking}/access/{token}/cancellations` | `guest.bookings.cancellations.store` | web, throttle:guest-token | — |
| GET | `auth/{provider}/callback` | `social.callback` | web | — |
| POST | `login` | `-` | web, guest, throttle:6,1 | — |
| POST | `register` | `-` | web, guest, platform.module:customer_registration, throttle:6,1 | customer_registration |
| GET | `auth/{provider}/redirect` | `social.redirect` | web, guest | — |
| GET | `storage/{path}` | `storage.local` | — | — |
| PUT | `storage/{path}` | `storage.local.upload` | — | — |

### auth (31)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| POST | `register/customer/validate-field` | `register.customer.validate-field` | web, guest, platform.module:customer_registration, throttle:register-validate-field | customer_registration |
| GET | `groups/{inventory}/passengers` | `group-ticketing.booking.passengers` | web, platform.module:public_umrah_groups, auth | public_umrah_groups |
| POST | `groups/{inventory}/passengers` | `group-ticketing.booking.passengers.store` | web, platform.module:public_umrah_groups, auth | public_umrah_groups |
| GET | `groups/booking/{groupBooking}/review` | `group-ticketing.booking.review` | web, platform.module:public_umrah_groups, auth | public_umrah_groups |
| POST | `groups/booking/{groupBooking}/review` | `group-ticketing.booking.review.confirm` | web, platform.module:public_umrah_groups, auth | public_umrah_groups |
| GET | `groups/booking/{groupBooking}/payment` | `group-ticketing.booking.payment` | web, platform.module:public_umrah_groups, auth | public_umrah_groups |
| POST | `groups/booking/{groupBooking}/payment` | `group-ticketing.booking.payment.submit` | web, platform.module:public_umrah_groups, auth | public_umrah_groups |
| GET | `groups/booking/{groupBooking}/confirmation` | `group-ticketing.booking.confirmation` | web, platform.module:public_umrah_groups, auth | public_umrah_groups |
| GET | `dashboard` | `dashboard` | web, auth | — |
| GET | `account/legacy` | `account.legacy` | web, auth | — |
| GET | `profile` | `profile.edit` | web, auth | — |
| PATCH | `profile` | `profile.update` | web, auth | — |
| DELETE | `profile` | `profile.destroy` | web, auth | — |
| GET | `login` | `login` | web, guest | — |
| GET | `register` | `register` | web, guest, platform.module:customer_registration | customer_registration |
| GET | `forgot-password` | `password.request` | web, guest | — |
| POST | `forgot-password` | `password.email` | web, guest | — |
| GET | `reset-password/{token}` | `password.reset` | web, guest | — |
| POST | `reset-password` | `password.store` | web, guest | — |
| GET | `verify-email/{id}/{hash}` | `verification.verify` | web, signed:relative, throttle:6,1 | — |
| GET | `password/force-change` | `password.force` | web, auth | — |
| POST | `password/force-change` | `password.force.store` | web, auth | — |
| GET | `verify-email` | `verification.notice` | web, auth | — |
| POST | `email/verification-notification` | `verification.send` | web, auth, throttle:6,1 | — |
| GET | `confirm-password` | `password.confirm` | web, auth | — |
| POST | `confirm-password` | `-` | web, auth | — |
| PUT | `password` | `password.update` | web, auth | — |
| GET | `auth/{provider}/link` | `social.link` | web, auth | — |
| GET | `auth/google/complete-profile` | `auth.google.complete-profile` | web, auth | — |
| POST | `auth/google/complete-profile` | `auth.google.complete-profile.store` | web, auth, throttle:6,1 | — |
| POST | `logout` | `logout` | web, auth | — |

### admin (199)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| GET | `admin` | `admin.dashboard` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/customers` | `admin.customers.index` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/customers/guests/show` | `admin.customers.guests.show` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/customers/{customer}` | `admin.customers.show` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/bookings` | `admin.bookings` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/bookings/data` | `admin.bookings.data` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/bookings/suggestions` | `admin.bookings.suggestions` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/bookings/{booking}/preview` | `admin.bookings.preview` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/bookings/{booking}` | `admin.bookings.show` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/{booking}/status` | `admin.bookings.status` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/notes` | `admin.bookings.notes` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/{booking}/assign-staff` | `admin.bookings.assign-staff` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/supplier-booking` | `admin.bookings.supplier-booking` | web, auth, agency.context, account.type:platform_admin, platform.module:supplier_booking | supplier_booking |
| POST | `admin/bookings/{booking}/prepare-supplier-pnr-context` | `admin.bookings.prepare-supplier-pnr-context` | web, auth, agency.context, account.type:platform_admin, platform.module:supplier_booking | supplier_booking |
| POST | `admin/bookings/{booking}/manual-pnr` | `admin.bookings.manual-pnr` | web, auth, agency.context, account.type:platform_admin, platform.module:supplier_booking | supplier_booking |
| POST | `admin/bookings/{booking}/sync-pnr-itinerary` | `admin.bookings.sync-pnr-itinerary` | web, auth, agency.context, account.type:platform_admin, platform.module:supplier_booking | supplier_booking |
| POST | `admin/bookings/{booking}/communication/send` | `admin.bookings.communication.send` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/communication/{communicationLog}/resend` | `admin.bookings.communication.resend` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/bookings/{booking}/audit/export` | `admin.bookings.audit.export` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/payments` | `admin.bookings.payments.store` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/payments/{bookingPayment}/verify` | `admin.bookings.payments.verify` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/payments/{bookingPayment}/reject` | `admin.bookings.payments.reject` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/cancellations` | `admin.bookings.cancellations.store` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/cancellations/{cancellationRequest}/approve` | `admin.bookings.cancellations.approve` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/cancellations/{cancellationRequest}/reject` | `admin.bookings.cancellations.reject` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/cancellations/{cancellationRequest}/process` | `admin.bookings.cancellations.process` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/refunds` | `admin.bookings.refunds.store` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/refunds/{bookingRefund}/approve` | `admin.bookings.refunds.approve` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/refunds/{bookingRefund}/mark-paid` | `admin.bookings.refunds.mark-paid` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/bookings/refunds/{bookingRefund}/reject` | `admin.bookings.refunds.reject` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/issue-ticket` | `admin.bookings.issue-ticket` | web, auth, agency.context, account.type:platform_admin, platform.module:ticketing | ticketing |
| POST | `admin/bookings/{booking}/documents/confirmation` | `admin.bookings.documents.confirmation` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/documents/invoice` | `admin.bookings.documents.invoice` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/documents/ticket-itinerary` | `admin.bookings.documents.ticket-itinerary` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/documents/refund-note` | `admin.bookings.documents.refund-note` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/{booking}/documents/cancellation-confirmation` | `admin.bookings.documents.cancellation-confirmation` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/bookings/payments/{bookingPayment}/documents/receipt` | `admin.bookings.payments.documents.receipt` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/bookings/documents/{bookingDocument}/download` | `admin.bookings.documents.download` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/commissions` | `admin.commissions.index` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/commissions/{agent}` | `admin.commissions.show` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/commissions/entries/{entry}/approve` | `admin.commissions.entries.approve` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/commissions/entries/{entry}/reject` | `admin.commissions.entries.reject` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/commissions/{agent}/adjustments` | `admin.commissions.adjustments.store` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/commissions/{agent}/payouts` | `admin.commissions.payouts.store` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/commissions/{agent}/statements` | `admin.commissions.statements.store` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agent-deposits` | `admin.agent-deposits.index` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_deposits | agent_deposits |
| GET | `admin/agent-deposits/{deposit}` | `admin.agent-deposits.show` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_deposits | agent_deposits |
| GET | `admin/agent-deposits/{deposit}/proof` | `admin.agent-deposits.proof` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_deposits | agent_deposits |
| PATCH | `admin/agent-deposits/{deposit}/approve` | `admin.agent-deposits.approve` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_deposits | agent_deposits |
| PATCH | `admin/agent-deposits/{deposit}/reject` | `admin.agent-deposits.reject` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_deposits | agent_deposits |
| GET | `admin/users` | `admin.users.index` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/users/create` | `admin.users.create` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/users` | `admin.users.store` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/users/{user}` | `admin.users.show` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/users/{user}/edit` | `admin.users.edit` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/users/{user}` | `admin.users.update` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/users/{user}/suspend` | `admin.users.suspend` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/users/{user}/activate` | `admin.users.activate` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/users/{user}/send-invite` | `admin.users.send-invite` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/users/{user}/reset-password-link` | `admin.users.reset-password-link` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agencies` | `admin.agencies.index` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agencies/{agency}` | `admin.agencies.show` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/agencies/{agency}/prefix` | `admin.agencies.prefix.update` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/agencies/{agency}/users/{user}/agency-role` | `admin.agencies.users.agency-role.update` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/agencies/{agency}/users/{user}/agent-permissions` | `admin.agencies.users.agent-permissions.update` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/agencies/{agency}/users/{user}/agent-permissions/apply-template` | `admin.agencies.users.agent-permissions.apply-template` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agents` | `admin.agents` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agents/data` | `admin.agents.data` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agents/suggestions` | `admin.agents.suggestions` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agents/search` | `admin.agents.search` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agents/export` | `admin.agents.export` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agents/{agent}/preview` | `admin.agents.preview` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/agent-applications/data` | `admin.agent-applications.data` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| GET | `admin/agent-applications/suggestions` | `admin.agent-applications.suggestions` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| GET | `admin/agent-applications` | `admin.agent-applications.index` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| GET | `admin/agent-applications/export` | `admin.agent-applications.export` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| GET | `admin/agent-applications/{application}` | `admin.agent-applications.show` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| PATCH | `admin/agent-applications/{application}/approve` | `admin.agent-applications.approve` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| PATCH | `admin/agent-applications/{application}/reject` | `admin.agent-applications.reject` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| PATCH | `admin/agent-applications/{application}/needs-more-info` | `admin.agent-applications.needs-more-info` | web, auth, agency.context, account.type:platform_admin, platform.module:agent_applications | agent_applications |
| GET | `admin/staff` | `admin.staff` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/cms-pages` | `admin.cms-pages.index` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/cms-pages/create` | `admin.cms-pages.create` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/cms-pages` | `admin.cms-pages.store` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/cms-pages/{cmsPage}/edit` | `admin.cms-pages.edit` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/cms-pages/{cmsPage}` | `admin.cms-pages.update` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/cms-pages/{cmsPage}/archive` | `admin.cms-pages.archive` | web, auth, agency.context, account.type:platform_admin | — |
| DELETE | `admin/cms-pages/{cmsPage}` | `admin.cms-pages.destroy` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/cms-pages/{cmsPage}/preview` | `admin.cms-pages.preview` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/promo-codes` | `admin.promo-codes.index` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/promo-codes/create` | `admin.promo-codes.create` | web, auth, agency.context, account.type:platform_admin | — |
| POST | `admin/promo-codes` | `admin.promo-codes.store` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/promo-codes/{promoCode}/edit` | `admin.promo-codes.edit` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/promo-codes/{promoCode}` | `admin.promo-codes.update` | web, auth, agency.context, account.type:platform_admin | — |
| PATCH | `admin/promo-codes/{promoCode}/toggle-status` | `admin.promo-codes.toggle-status` | web, auth, agency.context, account.type:platform_admin | — |
| GET | `admin/markups` | `admin.markups` | web, auth, agency.context, account.type:platform_admin, platform.module:markup_settings | markup_settings |
| GET | `admin/markups/create` | `admin.markups.create` | web, auth, agency.context, account.type:platform_admin, platform.module:markup_settings | markup_settings |
| POST | `admin/markups` | `admin.markups.store` | web, auth, agency.context, account.type:platform_admin, platform.module:markup_settings | markup_settings |
| GET | `admin/markups/{markupRule}/edit` | `admin.markups.edit` | web, auth, agency.context, account.type:platform_admin, platform.module:markup_settings | markup_settings |
| PATCH | `admin/markups/{markupRule}` | `admin.markups.update` | web, auth, agency.context, account.type:platform_admin, platform.module:markup_settings | markup_settings |

_... +99 more routes in this bucket._

### staff (42)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| GET | `staff` | `staff.dashboard` | web, auth, agency.context, account.type:staff | — |
| GET | `staff/bookings` | `staff.bookings.index` | web, auth, agency.context, account.type:staff | — |
| GET | `staff/bookings/{booking}` | `staff.bookings.show` | web, auth, agency.context, account.type:staff | — |
| PATCH | `staff/bookings/{booking}/status` | `staff.bookings.status` | web, auth, agency.context, account.type:staff | — |
| POST | `staff/bookings/{booking}/notes` | `staff.bookings.notes` | web, auth, agency.context, account.type:staff | — |
| POST | `staff/bookings/{booking}/supplier-booking` | `staff.bookings.supplier-booking` | web, auth, agency.context, account.type:staff, staff.permission:staff.bookings.update_status, platform.module:supplier_booking | supplier_booking |
| POST | `staff/bookings/{booking}/prepare-supplier-pnr-context` | `staff.bookings.prepare-supplier-pnr-context` | web, auth, agency.context, account.type:staff, staff.permission:staff.bookings.update_status, platform.module:supplier_booking | supplier_booking |
| POST | `staff/bookings/{booking}/manual-pnr` | `staff.bookings.manual-pnr` | web, auth, agency.context, account.type:staff, staff.permission:staff.bookings.update_status, platform.module:supplier_booking | supplier_booking |
| POST | `staff/bookings/{booking}/sync-pnr-itinerary` | `staff.bookings.sync-pnr-itinerary` | web, auth, agency.context, account.type:staff, staff.permission:staff.bookings.update_status, platform.module:supplier_booking | supplier_booking |
| POST | `staff/bookings/{booking}/payments` | `staff.bookings.payments.store` | web, auth, agency.context, account.type:staff | — |
| PATCH | `staff/bookings/payments/{bookingPayment}/verify` | `staff.bookings.payments.verify` | web, auth, agency.context, account.type:staff, staff.permission:staff.payments.verify | — |
| PATCH | `staff/bookings/payments/{bookingPayment}/reject` | `staff.bookings.payments.reject` | web, auth, agency.context, account.type:staff, staff.permission:staff.payments.reject | — |
| POST | `staff/bookings/{booking}/cancellations` | `staff.bookings.cancellations.store` | web, auth, agency.context, account.type:staff | — |
| PATCH | `staff/bookings/cancellations/{cancellationRequest}/approve` | `staff.bookings.cancellations.approve` | web, auth, agency.context, account.type:staff, staff.permission:staff.cancellations.approve | — |
| PATCH | `staff/bookings/cancellations/{cancellationRequest}/reject` | `staff.bookings.cancellations.reject` | web, auth, agency.context, account.type:staff, staff.permission:staff.cancellations.approve | — |
| PATCH | `staff/bookings/cancellations/{cancellationRequest}/process` | `staff.bookings.cancellations.process` | web, auth, agency.context, account.type:staff, staff.permission:staff.cancellations.process | — |
| POST | `staff/bookings/{booking}/refunds` | `staff.bookings.refunds.store` | web, auth, agency.context, account.type:staff | — |
| PATCH | `staff/bookings/refunds/{bookingRefund}/approve` | `staff.bookings.refunds.approve` | web, auth, agency.context, account.type:staff, staff.permission:staff.refunds.approve | — |
| PATCH | `staff/bookings/refunds/{bookingRefund}/mark-paid` | `staff.bookings.refunds.mark-paid` | web, auth, agency.context, account.type:staff, staff.permission:staff.refunds.mark_paid | — |
| PATCH | `staff/bookings/refunds/{bookingRefund}/reject` | `staff.bookings.refunds.reject` | web, auth, agency.context, account.type:staff, staff.permission:staff.refunds.reject | — |
| POST | `staff/bookings/{booking}/issue-ticket` | `staff.bookings.issue-ticket` | web, auth, agency.context, account.type:staff, staff.permission:staff.ticketing.issue, platform.module:ticketing | ticketing |
| POST | `staff/bookings/{booking}/documents/confirmation` | `staff.bookings.documents.confirmation` | web, auth, agency.context, account.type:staff | — |
| POST | `staff/bookings/{booking}/documents/invoice` | `staff.bookings.documents.invoice` | web, auth, agency.context, account.type:staff | — |
| POST | `staff/bookings/{booking}/documents/ticket-itinerary` | `staff.bookings.documents.ticket-itinerary` | web, auth, agency.context, account.type:staff | — |
| POST | `staff/bookings/{booking}/documents/refund-note` | `staff.bookings.documents.refund-note` | web, auth, agency.context, account.type:staff | — |
| POST | `staff/bookings/{booking}/documents/cancellation-confirmation` | `staff.bookings.documents.cancellation-confirmation` | web, auth, agency.context, account.type:staff | — |
| POST | `staff/bookings/payments/{bookingPayment}/documents/receipt` | `staff.bookings.payments.documents.receipt` | web, auth, agency.context, account.type:staff | — |
| GET | `staff/bookings/documents/{bookingDocument}/download` | `staff.bookings.documents.download` | web, auth, agency.context, account.type:staff | — |
| GET | `staff/ledger` | `staff.ledger.index` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.ledger.view | finance_reports |
| GET | `staff/ledger/{transaction}` | `staff.ledger.show` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.ledger.view | finance_reports |
| GET | `staff/accounting/ledger` | `staff.accounting.ledger.index` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.ledger.view | finance_reports |
| GET | `staff/accounting/ledger/{ledgerTransaction}` | `staff.accounting.ledger.show` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.ledger.view | finance_reports |
| GET | `staff/accounting/reconciliation` | `staff.accounting.reconciliation.index` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.ledger.view | finance_reports |
| GET | `staff/reports` | `staff.reports.index` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.reports.view | finance_reports |
| GET | `staff/finance/statements` | `staff.finance.statements.index` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.reports.view | finance_reports |
| GET | `staff/finance/statements/{agency}` | `staff.finance.statements.show` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.reports.view | finance_reports |
| GET | `staff/finance/statements/{agency}/export` | `staff.finance.statements.export` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.reports.export | finance_reports |
| GET | `staff/reports/export/{type}` | `staff.reports.export` | web, auth, agency.context, account.type:staff, platform.module:finance_reports, staff.permission:staff.reports.export | finance_reports |
| GET | `staff/support/tickets` | `staff.support.tickets.index` | web, auth, agency.context, account.type:staff, platform.module:support_system | support_system |
| GET | `staff/support/tickets/{ticket}` | `staff.support.tickets.show` | web, auth, agency.context, account.type:staff, platform.module:support_system | support_system |
| POST | `staff/support/tickets/{ticket}/reply` | `staff.support.tickets.reply` | web, auth, agency.context, account.type:staff, platform.module:support_system | support_system |
| PATCH | `staff/support/tickets/{ticket}/status` | `staff.support.tickets.status` | web, auth, agency.context, account.type:staff, platform.module:support_system | support_system |

### agent (47)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| GET | `agent/register` | `agent.register` | web, platform.module:agent_applications | agent_applications |
| GET | `agent/register/apply` | `agent.register.form` | web, platform.module:agent_applications | agent_applications |
| POST | `agent/register/validate-field` | `agent.register.validate-field` | web, platform.module:agent_applications, throttle:register-validate-field | agent_applications |
| POST | `agent/register` | `agent.register.store` | web, platform.module:agent_applications, throttle:6,1 | agent_applications |
| GET | `agent/register/submitted` | `agent.register.submitted` | web, platform.module:agent_applications | agent_applications |
| GET | `agent` | `agent.dashboard` | web, auth, agency.context, account.type:agent,agent_staff | — |
| GET | `agent/agency` | `agent.agency.show` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.agency.view | — |
| GET | `agent/agency/edit` | `agent.agency.edit` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.agency.edit | — |
| PATCH | `agent/agency` | `agent.agency.update` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.agency.edit | — |
| GET | `agent/staff` | `agent.staff.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| GET | `agent/staff/create` | `agent.staff.create` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| POST | `agent/staff` | `agent.staff.store` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| GET | `agent/staff/{staff}/edit` | `agent.staff.edit` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| PATCH | `agent/staff/{staff}` | `agent.staff.update` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| PATCH | `agent/staff/{staff}/agency-role` | `agent.staff.agency-role.update` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| PATCH | `agent/staff/{staff}/permissions` | `agent.staff.permissions.update` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| POST | `agent/staff/{staff}/permissions/apply-template` | `agent.staff.permissions.apply-template` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| DELETE | `agent/staff/{staff}` | `agent.staff.destroy` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.staff.manage, platform.module:agent_staff | agent_staff |
| GET | `agent/bookings/create` | `agent.bookings.create` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.bookings.create | — |
| POST | `agent/bookings` | `agent.bookings.store` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.bookings.create | — |
| GET | `agent/bookings` | `agent.bookings.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.bookings.view | — |
| GET | `agent/bookings/{booking}` | `agent.bookings.show` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.bookings.view | — |
| POST | `agent/bookings/{booking}/cancellations` | `agent.bookings.cancellations.store` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.bookings.view | — |
| POST | `agent/bookings/{booking}/payment-proof` | `agent.bookings.payment-proof` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.payments.upload, platform.module:payment_proofs… | payment_proofs |
| GET | `agent/commissions` | `agent.commissions.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.admin | — |
| GET | `agent/commissions/statements/{statement}` | `agent.commissions.statements.show` | web, auth, agency.context, account.type:agent,agent_staff, agent.admin | — |
| GET | `agent/wallet` | `agent.wallet.show` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.wallet.view, platform.module:agent_wallet | agent_wallet |
| GET | `agent/deposits` | `agent.deposits.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.wallet.view, platform.module:agent_deposits | agent_deposits |
| GET | `agent/ledger` | `agent.ledger.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.ledger.view, platform.module:agent_ledger | agent_ledger |
| GET | `agent/accounting/ledger` | `agent.accounting.ledger.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.ledger.view, platform.module:agent_ledger | agent_ledger |
| GET | `agent/accounting/ledger/{ledgerTransaction}` | `agent.accounting.ledger.show` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.ledger.view, platform.module:agent_ledger | agent_ledger |
| GET | `agent/reports` | `agent.reports.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.reports.view, platform.module:agent_reports | agent_reports |
| GET | `agent/finance/statement` | `agent.finance.statement.show` | web, auth, agency.context, account.type:agent,agent_staff | — |
| GET | `agent/finance/statement/export` | `agent.finance.statement.export` | web, auth, agency.context, account.type:agent,agent_staff | — |
| GET | `agent/deposits/create` | `agent.deposits.create` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.payments.upload, platform.module:agent_deposits | agent_deposits |
| POST | `agent/deposits` | `agent.deposits.store` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.payments.upload, platform.module:agent_deposits | agent_deposits |
| GET | `agent/travelers` | `agent.travelers.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.travelers.manage, platform.module:saved_travelers | saved_travelers |
| GET | `agent/travelers/create` | `agent.travelers.create` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.travelers.manage, platform.module:saved_travelers | saved_travelers |
| POST | `agent/travelers` | `agent.travelers.store` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.travelers.manage, platform.module:saved_travelers | saved_travelers |
| GET | `agent/travelers/{traveler}/edit` | `agent.travelers.edit` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.travelers.manage, platform.module:saved_travelers | saved_travelers |
| PATCH | `agent/travelers/{traveler}` | `agent.travelers.update` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.travelers.manage, platform.module:saved_travelers | saved_travelers |
| DELETE | `agent/travelers/{traveler}` | `agent.travelers.destroy` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.travelers.manage, platform.module:saved_travelers | saved_travelers |
| GET | `agent/support/tickets` | `agent.support.tickets.index` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.support.manage, platform.module:agent_support | agent_support |
| GET | `agent/support/tickets/create` | `agent.support.tickets.create` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.support.manage, platform.module:agent_support | agent_support |
| POST | `agent/support/tickets` | `agent.support.tickets.store` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.support.manage, platform.module:agent_support | agent_support |
| GET | `agent/support/tickets/{ticket}` | `agent.support.tickets.show` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.support.manage, platform.module:agent_support | agent_support |
| POST | `agent/support/tickets/{ticket}/reply` | `agent.support.tickets.reply` | web, auth, agency.context, account.type:agent,agent_staff, agent.permission:agent.support.manage, platform.module:agent_support | agent_support |

### customer (19)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| GET | `customer` | `customer.dashboard` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:customer_portal | customer_portal |
| GET | `customer/bookings` | `customer.bookings.index` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:customer_portal | customer_portal |
| GET | `customer/bookings/{booking}` | `customer.bookings.show` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:customer_portal | customer_portal |
| GET | `customer/documents/{bookingDocument}/download` | `customer.documents.download` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:customer_portal | customer_portal |
| POST | `customer/bookings/{booking}/payment-proof` | `customer.bookings.payment-proof` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:payment_proofs… | payment_proofs |
| POST | `customer/bookings/{booking}/cancellations` | `customer.bookings.cancellations.store` | web, auth, agency.context, account.type:customer, customer.email.portal.verified | — |
| GET | `customer/travelers` | `customer.travelers.index` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:saved_travelers | saved_travelers |
| GET | `customer/travelers/create` | `customer.travelers.create` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:saved_travelers | saved_travelers |
| POST | `customer/travelers` | `customer.travelers.store` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:saved_travelers | saved_travelers |
| GET | `customer/travelers/{traveler}/edit` | `customer.travelers.edit` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:saved_travelers | saved_travelers |
| PATCH | `customer/travelers/{traveler}` | `customer.travelers.update` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:saved_travelers | saved_travelers |
| DELETE | `customer/travelers/{traveler}` | `customer.travelers.destroy` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:saved_travelers | saved_travelers |
| GET | `customer/support` | `customer.support.index` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:support_system | support_system |
| GET | `customer/support/tickets` | `customer.support.tickets.index` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:support_system | support_system |
| GET | `customer/support/tickets/create` | `customer.support.tickets.create` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:support_system | support_system |
| POST | `customer/support/tickets` | `customer.support.tickets.store` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:support_system | support_system |
| GET | `customer/support/tickets/{ticket}` | `customer.support.tickets.show` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:support_system | support_system |
| POST | `customer/support/tickets/{ticket}/reply` | `customer.support.tickets.reply` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:support_system | support_system |
| PATCH | `customer/support/tickets/{ticket}/close` | `customer.support.tickets.close` | web, auth, agency.context, account.type:customer, customer.email.portal.verified, platform.module:support_system | support_system |

### dev_cp (21)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| GET | `dev/cp/login` | `dev.cp.login` | web | — |
| POST | `dev/cp/login` | `dev.cp.login.store` | web, throttle:6,1 | — |
| POST | `dev/cp/logout` | `dev.cp.logout` | web, developer.cp | — |
| GET | `dev/cp/password` | `dev.cp.password` | web, developer.cp | — |
| POST | `dev/cp/password` | `dev.cp.password.store` | web, developer.cp | — |
| GET | `dev/cp` | `dev.cp.index` | web, developer.cp | — |
| GET | `dev/cp/companies` | `dev.cp.companies.index` | web, developer.cp | — |
| POST | `dev/cp/companies/{agency}/package` | `dev.cp.companies.package` | web, developer.cp | — |
| POST | `dev/cp/companies/{agency}/modules` | `dev.cp.companies.modules` | web, developer.cp | — |
| GET | `dev/cp/users` | `dev.cp.users.index` | web, developer.cp | — |
| GET | `dev/cp/security-events` | `dev.cp.security-events.index` | web, developer.cp | — |
| GET | `dev/cp/health` | `dev.cp.health` | web, developer.cp | — |
| GET | `dev/cp/sabre-status` | `dev.cp.sabre` | web, developer.cp | — |
| GET | `dev/cp/group-ticketing` | `dev.cp.group-ticketing` | web, developer.cp | — |
| GET | `dev/cp/dashboards` | `dev.cp.dashboards` | web, developer.cp | — |
| GET | `dev/cp/deployment` | `dev.cp.deployment` | web, developer.cp | — |
| GET | `dev/cp/modules` | `dev.cp.modules.index` | web, developer.cp | — |
| POST | `dev/cp/modules` | `dev.cp.modules.update` | web, developer.cp | — |
| POST | `dev/cp/modules/preset` | `dev.cp.modules.preset` | web, developer.cp | — |
| POST | `dev/cp/modules/reset` | `dev.cp.modules.reset` | web, developer.cp | — |
| POST | `dev/cp/modules/emergency-reset` | `dev.cp.modules.emergency-reset` | web, developer.cp | — |

### api (0)

_No routes in this bucket._

### health (1)

| Methods | URI | Name | Middleware | Platform module |
|---------|-----|------|------------|-----------------|
| GET | `up` | `-` | — | — |

### unclassified (0)

_No routes in this bucket._

## Mutating routes possibly missing auth

- `[POST] view-preference/mobile name=view-preference.mobile mw=web`
- `[POST] view-preference/desktop name=view-preference.desktop mw=web`
- `[POST] support name=support.store mw=web,platform.module:support_system,throttle:10,1`
- `[GET|POST|PUT|PATCH|DELETE|OPTIONS] contact name=- mw=web`
- `[POST] agent/register/validate-field name=agent.register.validate-field mw=web,platform.module:agent_applications,throttle:register-validate-field`
- `[POST] agent/register name=agent.register.store mw=web,platform.module:agent_applications,throttle:6,1`
- `[GET|POST|PUT|PATCH|DELETE|OPTIONS] agent-network name=agent-network mw=web`
- `[GET|POST|PUT|PATCH|DELETE|OPTIONS] register/customer name=- mw=web`
- `[GET|POST|PUT|PATCH|DELETE|OPTIONS] register/agent name=- mw=web`
- `[POST] register/customer/validate-field name=register.customer.validate-field mw=web,guest,platform.module:customer_registration,throttle:register-validate-field`
- `[GET|POST|PUT|PATCH|DELETE|OPTIONS] flights/search name=flights.search mw=web`
- `[POST] flights/results/revalidate-offer name=flights.results.revalidate-offer mw=web,platform.module:public_flight_search,throttle:public-flight-results-data`
- `[POST] flights/select-return-combo name=flights.select-return-combo mw=web,platform.module:public_flight_search`
- `[POST] login name=- mw=web,guest,throttle:6,1`
- `[POST] register name=- mw=web,guest,platform.module:customer_registration,throttle:6,1`
- `[PUT] storage/{path} name=storage.local.upload mw=`

