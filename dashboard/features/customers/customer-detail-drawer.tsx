"use client";

import Link from "next/link";
import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import { AccountStatusBadge, VerificationStatusBadge } from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { CustomerRecord } from "@/types/customer";

export function CustomerDetailDrawerContent({ customer }: { customer: CustomerRecord }) {
  const recentBookings = customer.linkedBookingIds.slice(0, 5);
  const recentTransactions = customer.linkedTransactionIds.slice(0, 5);

  return (
    <div className="space-y-5" data-testid="customer-drawer-content">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="customer-overview-heading">
        <h3 id="customer-overview-heading" className="text-sm font-semibold text-gray-900">
          Overview
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Customer ID</dt>
            <dd className="font-medium">{customer.id}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Full name</dt>
            <dd>{customer.fullName}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Customer type</dt>
            <dd>{customer.customerType}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Created</dt>
            <dd>{formatDate(customer.createdDate)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="customer-contact-heading">
        <h3 id="customer-contact-heading" className="text-sm font-semibold text-gray-900">
          Contact details
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div>
            <dt className="text-jp-muted">Email</dt>
            <dd>
              <a className="text-jp-accent-muted underline" href={`mailto:${customer.email}`}>
                {customer.email}
              </a>
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Phone</dt>
            <dd>{customer.phone}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Location</dt>
            <dd>
              {customer.city}, {customer.country}
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Nationality</dt>
            <dd>{customer.nationality}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Preferred contact</dt>
            <dd className="capitalize">{customer.preferredContactMethod}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="customer-status-heading">
        <h3 id="customer-status-heading" className="text-sm font-semibold text-gray-900">
          Account and verification
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <AccountStatusBadge status={customer.accountStatus} />
          <VerificationStatusBadge status={customer.verificationStatus} />
        </div>
      </section>

      <Divider />

      <section aria-labelledby="customer-travellers-heading">
        <h3 id="customer-travellers-heading" className="text-sm font-semibold text-gray-900">
          Travellers ({customer.travellerCount})
        </h3>
        {customer.travellers.length > 0 ? (
          <ul className="mt-2 space-y-2 text-sm">
            {customer.travellers.map((traveller) => (
              <li key={traveller.id} className="rounded-lg bg-gray-50 p-3">
                <p className="font-medium">{traveller.name}</p>
                <p className="text-xs text-jp-muted">
                  {traveller.ageGroup} · {traveller.nationality} · {traveller.relationshipToPrimary}
                </p>
                <p className="text-xs text-jp-muted">
                  Passport: {traveller.passportStatus}
                  {traveller.frequentTraveller ? " · Frequent traveller" : ""}
                </p>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No traveller profiles on file.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="customer-financial-heading">
        <h3 id="customer-financial-heading" className="text-sm font-semibold text-gray-900">
          Financial summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Total booked</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(customer.totalBookedValue, customer.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Total paid</dt>
            <dd className="tabular-nums">{formatCurrency(customer.totalPaid, customer.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Outstanding</dt>
            <dd className="tabular-nums">
              {formatCurrency(customer.outstandingBalance, customer.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Refunds</dt>
            <dd className="tabular-nums">{formatCurrency(customer.refundTotal, customer.currency)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="customer-bookings-heading">
        <h3 id="customer-bookings-heading" className="text-sm font-semibold text-gray-900">
          Linked bookings
        </h3>
        {recentBookings.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentBookings.map((bookingId) => (
              <li key={bookingId}>
                <Link
                  href={`/bookings?id=${bookingId}`}
                  className="text-jp-accent-muted underline"
                >
                  {bookingId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked bookings.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="customer-payments-heading">
        <h3 id="customer-payments-heading" className="text-sm font-semibold text-gray-900">
          Linked payments
        </h3>
        {recentTransactions.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentTransactions.map((txId) => (
              <li key={txId}>
                <Link
                  href={`/payments?transactionId=${txId}`}
                  className="text-jp-accent-muted underline"
                >
                  {txId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked transactions.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="customer-activity-heading">
        <h3 id="customer-activity-heading" className="text-sm font-semibold text-gray-900">
          Activity summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Bookings</dt>
            <dd>
              {customer.bookingCount} total · {customer.completedBookingCount} completed ·{" "}
              {customer.cancelledBookingCount} cancelled
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last booking</dt>
            <dd>{customer.lastBookingDate ? formatDate(customer.lastBookingDate) : "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last payment</dt>
            <dd>{customer.lastPaymentDate ? formatDate(customer.lastPaymentDate) : "—"}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="customer-notes-heading">
        <h3 id="customer-notes-heading" className="text-sm font-semibold text-gray-900">
          Notes
        </h3>
        <p className="mt-2 text-sm text-gray-700">{customer.notesSummary}</p>
        <p className="mt-2 text-xs text-jp-muted">
          Read-only preview — no customer actions are available in this module.
        </p>
      </section>
    </div>
  );
}
