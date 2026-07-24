"use client";

import Link from "next/link";
import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import {
  CredentialStatusBadge,
  IntegrationStatusBadge,
  OperationalStatusBadge,
  SettlementStatusBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { SupplierRecord } from "@/types/supplier";

export function SupplierDetailDrawerContent({ supplier }: { supplier: SupplierRecord }) {
  const recentBookings = supplier.linkedBookingIds.slice(0, 5);
  const recentTransactions = supplier.linkedTransactionIds.slice(0, 5);

  return (
    <div className="space-y-5" data-testid="supplier-drawer-content">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="supplier-overview-heading">
        <h3 id="supplier-overview-heading" className="text-sm font-semibold text-gray-900">
          Overview
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Supplier ID</dt>
            <dd className="font-medium">{supplier.id}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Name</dt>
            <dd>{supplier.supplierName}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Display code</dt>
            <dd>{supplier.displayCode}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Created</dt>
            <dd>{formatDate(supplier.createdDate)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="supplier-category-heading">
        <h3 id="supplier-category-heading" className="text-sm font-semibold text-gray-900">
          Category and region
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Category</dt>
            <dd>{supplier.supplierCategory}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Operating region</dt>
            <dd>{supplier.operatingRegion}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="supplier-status-heading">
        <h3 id="supplier-status-heading" className="text-sm font-semibold text-gray-900">
          Operational status
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <OperationalStatusBadge status={supplier.operationalStatus} />
          <IntegrationStatusBadge status={supplier.integrationStatus} />
        </div>
      </section>

      <Divider />

      <section aria-labelledby="supplier-credential-heading">
        <h3 id="supplier-credential-heading" className="text-sm font-semibold text-gray-900">
          Credential status
        </h3>
        <div className="mt-2">
          <CredentialStatusBadge status={supplier.credentialStatus} />
        </div>
        <p className="mt-2 text-xs text-jp-muted">
          Abstract status only — no credentials, API keys, or secrets are displayed in preview.
        </p>
      </section>

      <Divider />

      <section aria-labelledby="supplier-financial-heading">
        <h3 id="supplier-financial-heading" className="text-sm font-semibold text-gray-900">
          Financial and settlement
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Settlement status</dt>
            <dd>
              <SettlementStatusBadge status={supplier.settlementStatus} />
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Total booking value</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(supplier.totalBookingValue, supplier.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Paid to supplier</dt>
            <dd className="tabular-nums">
              {formatCurrency(supplier.totalPaidToSupplier, supplier.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Outstanding settlement</dt>
            <dd className="tabular-nums">
              {formatCurrency(supplier.outstandingSettlement, supplier.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Refund exposure</dt>
            <dd className="tabular-nums">
              {formatCurrency(supplier.refundExposure, supplier.currency)}
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="supplier-bookings-heading">
        <h3 id="supplier-bookings-heading" className="text-sm font-semibold text-gray-900">
          Linked bookings
        </h3>
        {recentBookings.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentBookings.map((bookingId) => (
              <li key={bookingId}>
                <Link href={`/bookings?id=${bookingId}`} className="text-jp-accent-muted underline">
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

      <section aria-labelledby="supplier-payments-heading">
        <h3 id="supplier-payments-heading" className="text-sm font-semibold text-gray-900">
          Linked payments
        </h3>
        {recentTransactions.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentTransactions.map((txId) => (
              <li key={txId}>
                <Link href={`/payments?transactionId=${txId}`} className="text-jp-accent-muted underline">
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

      <section aria-labelledby="supplier-contact-heading">
        <h3 id="supplier-contact-heading" className="text-sm font-semibold text-gray-900">
          Contact and escalation
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div>
            <dt className="text-jp-muted">Support contact</dt>
            <dd>{supplier.supportContact}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Escalation contact</dt>
            <dd>{supplier.escalationContact}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="supplier-activity-heading">
        <h3 id="supplier-activity-heading" className="text-sm font-semibold text-gray-900">
          Activity summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Bookings</dt>
            <dd>
              {supplier.bookingCount} total · {supplier.confirmedBookingCount} confirmed ·{" "}
              {supplier.failedBookingCount} failed
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last booking activity</dt>
            <dd>
              {supplier.lastBookingActivity ? formatDate(supplier.lastBookingActivity) : "—"}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last settlement activity</dt>
            <dd>
              {supplier.lastSettlementActivity ? formatDate(supplier.lastSettlementActivity) : "—"}
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="supplier-notes-heading">
        <h3 id="supplier-notes-heading" className="text-sm font-semibold text-gray-900">
          Notes
        </h3>
        <p className="mt-2 text-sm text-gray-700">{supplier.notesSummary}</p>
        <p className="mt-2 text-xs text-jp-muted">
          Read-only preview — no supplier actions, credential editing, or live API tests are available.
        </p>
      </section>
    </div>
  );
}
