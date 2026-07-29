"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchCustomerPayments } from "../services/customer-dashboard-api";
import { CustomerDashboardErrorState, CustomerDashboardShell, CustomerEmptyState, StatusBadge } from "../shell/CustomerDashboardShell";
import type { CustomerPayment } from "../types";
import type { PublicSession } from "@/types/session";

export function CustomerPaymentsPage({ session }: { session: PublicSession }) {
  const [payments, setPayments] = useState<CustomerPayment[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const result = await fetchCustomerPayments({});
      if (!result.ok) setError(result.message);
      else setPayments(result.data.payments);
      setLoading(false);
    })();
  }, []);

  return (
    <CustomerDashboardShell session={session} title="Payment history">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading payments…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} /> : null}
      {!loading && !error && payments.length === 0 ? (
        <CustomerEmptyState title="No payments yet" description="Payment activity will appear here after you book." />
      ) : null}
      <div className="space-y-3" data-testid="customer-payments-list">
        {payments.map((payment) => (
          <article key={`${payment.source}-${payment.reference}`} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">{payment.reference}</p>
                <p className="text-jp-sm text-jp-muted">
                  {payment.booking_reference ? (
                    <Link href={`/customer/bookings/${payment.booking_reference}`} className="text-jp-primary">
                      {payment.booking_reference}
                    </Link>
                  ) : (
                    "Booking unavailable"
                  )}
                </p>
                <p className="text-jp-sm text-jp-muted">{payment.payment_method_label}</p>
              </div>
              <div className="text-right">
                <p className="font-semibold">
                  {payment.currency} {payment.amount.toLocaleString()}
                </p>
                <StatusBadge status={payment.payment_status} />
              </div>
            </div>
          </article>
        ))}
      </div>
    </CustomerDashboardShell>
  );
}
