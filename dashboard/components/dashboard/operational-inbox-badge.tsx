"use client";

import { useEffect, useState } from "react";
import { DashboardLink } from "@/components/dashboard/dashboard-link";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { dashboardApiUrl, DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";

type AlertItem = {
  key: string;
  label: string;
  count: number;
  href: string;
};

function hrefForKey(key: string): string {
  return (
    {
      agency_applications_pending: "/agents/applications",
      pending_deposits: "/deposits",
      payment_review: "/payments",
      commissions_requiring_review: "/commissions",
    }[key] ?? "/dashboard"
  );
}

export function OperationalInboxBadge() {
  const isLive = useDashboardLiveMode();
  const [alerts, setAlerts] = useState<AlertItem[]>([]);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!isLive) {
      return;
    }
    void fetch(dashboardApiUrl(DASHBOARD_API_ROUTES.overview), {
      credentials: "include",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      cache: "no-store",
    })
      .then((response) => response.json())
      .then((body: { data?: { operationalCounts?: Record<string, number> } }) => {
        const counts = body.data?.operationalCounts ?? {};
        const items: AlertItem[] = [
          "agency_applications_pending",
          "pending_deposits",
          "payment_review",
          "commissions_requiring_review",
        ]
          .map((key) => ({
            key,
            label: key.replaceAll("_", " "),
            count: Number(counts[key] ?? 0),
            href: hrefForKey(key),
          }))
          .filter((item) => item.count > 0);
        setAlerts(items);
      })
      .catch(() => setAlerts([]));
  }, [isLive]);

  const total = alerts.reduce((sum, item) => sum + item.count, 0);
  if (!isLive || total === 0) {
    return null;
  }

  return (
    <div className="relative">
      <button
        type="button"
        data-testid="operational-inbox-badge"
        className="relative flex min-h-11 items-center rounded-xl border border-jp-border px-3 text-sm"
        aria-expanded={open}
        aria-label="Operational inbox"
        onClick={() => setOpen((value) => !value)}
      >
        Inbox
        <span className="ml-2 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white">{total}</span>
      </button>
      {open ? (
        <div className="absolute right-0 z-40 mt-2 w-72 rounded-xl border border-jp-border bg-white p-2 shadow-lg">
          <ul className="space-y-1">
            {alerts.map((alert) => (
              <li key={alert.key}>
                <DashboardLink
                  href={alert.href}
                  className="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-gray-50"
                  onClick={() => setOpen(false)}
                >
                  <span className="capitalize">{alert.label}</span>
                  <span className="font-semibold tabular-nums">{alert.count}</span>
                </DashboardLink>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
