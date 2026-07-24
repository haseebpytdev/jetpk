"use client";

import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import {
  LedgerPaymentStatusBadge,
  ReconciliationStatusBadge,
  TransactionStatusBadge,
  TransactionTypeBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate, formatDateTime } from "@/lib/format";
import type { TransactionRecord } from "@/types/payment";

function refOrDash(value: string | null): string {
  return value ?? "—";
}

export function PaymentDetailDrawerContent({ transaction }: { transaction: TransactionRecord }) {
  return (
    <div className="space-y-5" data-testid="payment-drawer-content">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="payment-id-heading">
        <h3 id="payment-id-heading" className="text-sm font-semibold text-gray-900">
          Identification
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Transaction ID</dt>
            <dd className="font-medium">{transaction.transactionId}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Payment ID</dt>
            <dd>{transaction.paymentId}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Booking ID</dt>
            <dd>{transaction.bookingId}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">PNR</dt>
            <dd>{transaction.pnr}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Supplier ref</dt>
            <dd>{refOrDash(transaction.supplierReference)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="customer-heading">
        <h3 id="customer-heading" className="text-sm font-semibold text-gray-900">
          Customer
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div>
            <dt className="text-jp-muted">Name</dt>
            <dd>{transaction.customerName}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Email</dt>
            <dd>
              <a className="text-jp-accent-muted underline" href={`mailto:${transaction.customerEmail}`}>
                {transaction.customerEmail}
              </a>
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Phone</dt>
            <dd>{transaction.customerPhone}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="payment-method-heading">
        <h3 id="payment-method-heading" className="text-sm font-semibold text-gray-900">
          Payment details
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Method</dt>
            <dd className="capitalize">{transaction.paymentMethod.replace(/_/g, " ")}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Channel</dt>
            <dd className="capitalize">{transaction.paymentChannel}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Type</dt>
            <dd>
              <TransactionTypeBadge type={transaction.transactionType} />
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Transaction date</dt>
            <dd>{formatDate(transaction.transactionDate)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Booking date</dt>
            <dd>{formatDate(transaction.bookingDate)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Created</dt>
            <dd>{formatDateTime(transaction.createdAt)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last updated</dt>
            <dd>{formatDateTime(transaction.updatedAt)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="references-heading">
        <h3 id="references-heading" className="text-sm font-semibold text-gray-900">
          References
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Gateway</dt>
            <dd>{refOrDash(transaction.gatewayReference)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Bank</dt>
            <dd>{refOrDash(transaction.bankReference)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Manual</dt>
            <dd>{refOrDash(transaction.manualReference)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="financial-heading">
        <h3 id="financial-heading" className="text-sm font-semibold text-gray-900">
          Financial
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Gross</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(transaction.grossAmount, transaction.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Fee</dt>
            <dd className="tabular-nums">{formatCurrency(transaction.feeAmount, transaction.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Net</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(transaction.netAmount, transaction.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Paid on booking</dt>
            <dd className="tabular-nums">{formatCurrency(transaction.paidAmount, transaction.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Outstanding</dt>
            <dd className="tabular-nums">
              {formatCurrency(transaction.outstandingAmount, transaction.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Refunded</dt>
            <dd className="tabular-nums">
              {formatCurrency(transaction.refundedAmount, transaction.currency)}
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="status-heading">
        <h3 id="status-heading" className="text-sm font-semibold text-gray-900">
          Status
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <LedgerPaymentStatusBadge status={transaction.paymentStatus} />
          <TransactionStatusBadge status={transaction.transactionStatus} />
          <ReconciliationStatusBadge status={transaction.reconciliationStatus} />
        </div>
        <p className="mt-3 text-sm text-jp-muted">Source: {transaction.sourceOrAgent}</p>
      </section>

      <Divider />

      <section aria-labelledby="audit-heading">
        <h3 id="audit-heading" className="text-sm font-semibold text-gray-900">
          Audit note
        </h3>
        <p className="mt-2 text-sm text-gray-700">{transaction.auditNote}</p>
        <p className="mt-2 text-xs text-jp-muted">
          This record is synthetic preview data. No payment actions are available in this module.
        </p>
      </section>
    </div>
  );
}
