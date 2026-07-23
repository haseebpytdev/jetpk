"use client";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import type { OverviewData } from "@/types/dashboard";

export function SummaryStatsRow({ summaryStats }: Pick<OverviewData, "summaryStats">) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
      {summaryStats.map((stat) => (
        <Card key={stat.key} className="p-4">
          <CardDescription>{stat.label}</CardDescription>
          <p className="mt-2 font-display text-xl font-bold tabular-nums sm:text-2xl">{stat.value}</p>
          <p
            className={`mt-1 text-xs font-medium ${
              stat.tone === "down" ? "text-red-600" : stat.tone === "warn" ? "text-amber-600" : "text-emerald-600"
            }`}
          >
            {stat.delta} vs yesterday
          </p>
        </Card>
      ))}
    </div>
  );
}

export function RecentNotificationsPanel({ recentNotifications }: Pick<OverviewData, "recentNotifications">) {
  return (
    <Card className="h-full">
      <CardTitle>Recent notifications</CardTitle>
      <CardDescription className="mt-1">Mock feed — not live comms</CardDescription>
      <ul className="mt-4 space-y-3">
        {recentNotifications.map((n) => (
          <li key={n.id} className="flex gap-3 rounded-xl border border-jp-border p-3">
            <span
              className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${
                n.tone === "success" ? "bg-emerald-500" : n.tone === "warn" ? "bg-amber-500" : "bg-blue-500"
              }`}
              aria-hidden
            />
            <div className="min-w-0">
              <p className="text-sm font-medium">{n.title}</p>
              <p className="text-xs text-jp-muted">{n.detail}</p>
              <p className="mt-1 text-xs text-gray-400">{n.time}</p>
            </div>
          </li>
        ))}
      </ul>
    </Card>
  );
}

export function RecentBookingsTable({ recentBookings }: Pick<OverviewData, "recentBookings">) {
  return (
    <Card className="overflow-hidden p-0">
      <div className="border-b border-jp-border p-4 sm:p-5">
        <CardTitle>Recent bookings</CardTitle>
        <CardDescription className="mt-1">Synthetic preview data</CardDescription>
      </div>
      <div className="overflow-x-auto">
        <table className="min-w-[640px] w-full text-left text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-jp-muted">
            <tr>
              <th className="px-4 py-3">PNR / ID</th>
              <th className="px-4 py-3">Customer</th>
              <th className="px-4 py-3">Route</th>
              <th className="px-4 py-3">Date</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Amount</th>
              <th className="px-4 py-3">Payment</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {recentBookings.map((row) => (
              <tr key={row.id} className="border-t border-jp-border">
                <td className="px-4 py-3 font-mono text-xs">{row.pnr}</td>
                <td className="px-4 py-3">
                  <div>{row.customer}</div>
                  <div className="text-xs text-jp-muted">{row.phone}</div>
                </td>
                <td className="px-4 py-3">{row.route}</td>
                <td className="px-4 py-3">{row.date}</td>
                <td className="px-4 py-3">
                  <Badge label={row.status} />
                </td>
                <td className="px-4 py-3 tabular-nums">{row.amount}</td>
                <td className="px-4 py-3">
                  <Badge label={row.payment} />
                </td>
                <td className="px-4 py-3">
                  <Button variant="ghost" size="sm" type="button" onClick={() => alert("Preview only — view booking")}>
                    View
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex flex-col gap-2 border-t border-jp-border p-4 text-xs text-jp-muted sm:flex-row sm:items-center sm:justify-between">
        <span>Showing 1 to {recentBookings.length} of {recentBookings.length} (mock)</span>
      </div>
    </Card>
  );
}

export function SidePanels({
  topRoutes,
  systemHealth,
}: Pick<OverviewData, "topRoutes" | "systemHealth">) {
  return (
    <div className="grid gap-4">
      <Card>
        <CardTitle>Top selling routes</CardTitle>
        <ul className="mt-4 space-y-3">
          {topRoutes.map((r) => (
            <li key={r.route}>
              <div className="flex justify-between text-sm">
                <span>{r.route}</span>
                <span className="tabular-nums text-jp-muted">{r.share}%</span>
              </div>
              <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                <div className="h-full rounded-full bg-jp-accent" style={{ width: `${r.share}%` }} />
              </div>
            </li>
          ))}
        </ul>
      </Card>
      <Card>
        <CardTitle>System health</CardTitle>
        <ul className="mt-4 space-y-2">
          {systemHealth.map((s) => (
            <li key={s.name} className="flex items-center justify-between text-sm">
              <span>{s.name}</span>
              <span className="flex items-center gap-2">
                <span
                  className={`h-2 w-2 rounded-full ${
                    s.status === "operational" ? "bg-emerald-500" : s.status === "degraded" ? "bg-amber-500" : "bg-red-500"
                  }`}
                  aria-hidden
                />
                <span className="capitalize">{s.status}</span>
              </span>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}

export function QuickActionsBar({
  actions,
}: {
  actions: OverviewData["shortcutActions"];
}) {
  return (
    <Card className="flex flex-wrap gap-2">
      {actions.map((a) => (
        <Button
          key={a.label}
          variant="secondary"
          size="sm"
          type="button"
          onClick={() => alert(`Preview — ${a.laravelRoute}${a.queue ? `?queue=${a.queue}` : ""}`)}
        >
          {a.label}
        </Button>
      ))}
    </Card>
  );
}
