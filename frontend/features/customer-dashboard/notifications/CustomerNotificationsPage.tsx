"use client";

import { useEffect, useState } from "react";
import { customerApiErrorMessage, fetchNotifications } from "../services/customer-dashboard-api";
import { CustomerDashboardErrorState, CustomerDashboardShell, CustomerEmptyState } from "../shell/CustomerDashboardShell";
import type { PublicSession } from "@/types/session";

export function CustomerNotificationsPage({ session }: { session: PublicSession }) {
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const result = await fetchNotifications();
      if (!result.ok) setError(customerApiErrorMessage(result));
      else setMessage(result.data.message ?? null);
      setLoading(false);
    })();
  }, []);

  return (
    <CustomerDashboardShell session={session} title="Notifications">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading notifications…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} /> : null}
      {!loading && !error ? (
        <div className="w-full">
          <CustomerEmptyState
            title="No in-app notifications"
            description={message ?? "Booking updates are sent to your registered email address."}
          />
        </div>
      ) : null}
    </CustomerDashboardShell>
  );
}
