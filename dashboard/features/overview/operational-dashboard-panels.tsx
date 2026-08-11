"use client";

import Link from "next/link";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { resolveOverviewDashboardHref } from "@/lib/overview-dashboard-href";
import { useDashboardPortal } from "@/lib/portal-context";
import type { OverviewData, PipelineStage, SupplierStatusItem } from "@/types/dashboard";

export function BookingPipelinePanel({ stages }: { stages: PipelineStage[] }) {
  const portal = useDashboardPortal();

  if (stages.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardTitle>Booking pipeline</CardTitle>
      <CardDescription className="mt-1">Counts from live booking workflow states.</CardDescription>
      <ul className="mt-4 space-y-2">
        {stages.map((stage) => {
          const href = resolveOverviewDashboardHref(portal, {
            laravelRoute: stage.laravelRoute,
            queue: stage.queue,
          });
          return (
            <li key={stage.key} className="flex items-center justify-between gap-3 rounded-lg border border-jp-border px-3 py-2">
              <span className="text-sm font-medium">{stage.label}</span>
              <Link href={href} className="text-sm font-semibold tabular-nums text-jp-accent-muted hover:underline">
                {stage.count}
              </Link>
            </li>
          );
        })}
      </ul>
    </Card>
  );
}

export function SupplierStatusPanel({ items }: { items: SupplierStatusItem[] }) {
  if (items.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardTitle>Supplier status</CardTitle>
      <CardDescription className="mt-1">Sanitized integration state — no credentials exposed.</CardDescription>
      <ul className="mt-4 space-y-3">
        {items.map((item) => (
          <li key={item.key} className="rounded-lg border border-jp-border px-3 py-2">
            <div className="flex items-center justify-between gap-2">
              <span className="text-sm font-medium">{item.label}</span>
              <span className="text-xs font-semibold uppercase tracking-wide text-jp-muted">{item.status}</span>
            </div>
            <p className="mt-1 text-xs text-jp-muted">{item.detail}</p>
          </li>
        ))}
      </ul>
    </Card>
  );
}

export function PaymentOperationsPanel({
  items,
}: {
  items: OverviewData["paymentOperations"];
}) {
  const portal = useDashboardPortal();

  if (items.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardTitle>Payment operations</CardTitle>
      <ul className="mt-4 space-y-2">
        {items.map((item) => {
          const href = resolveOverviewDashboardHref(portal, {
            laravelRoute: item.laravelRoute,
            queue: item.queue,
          });
          return (
            <li key={item.key} className="flex items-center justify-between gap-3 rounded-lg border border-jp-border px-3 py-2">
              <span className="text-sm">{item.label}</span>
              <Link href={href} className="text-sm font-semibold tabular-nums text-jp-accent-muted hover:underline">
                {item.count}
              </Link>
            </li>
          );
        })}
      </ul>
    </Card>
  );
}

export function SupportOperationsPanel({
  items,
}: {
  items: OverviewData["supportOperations"];
}) {
  const portal = useDashboardPortal();

  if (items.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardTitle>Support workload</CardTitle>
      <ul className="mt-4 space-y-2">
        {items.map((item) => {
          const href = resolveOverviewDashboardHref(portal, {
            laravelRoute: item.laravelRoute,
            queue: item.queue,
          });
          return (
            <li key={item.key} className="rounded-lg border border-jp-border px-3 py-2">
              <div className="flex items-center justify-between gap-3">
                <span className="text-sm font-medium">{item.label}</span>
                <Link href={href} className="text-sm font-semibold tabular-nums text-jp-accent-muted hover:underline">
                  {item.count}
                </Link>
              </div>
              {item.helper ? <p className="mt-1 text-xs text-jp-muted">{item.helper}</p> : null}
            </li>
          );
        })}
      </ul>
    </Card>
  );
}

export function SystemHealthPanel({ items }: { items: OverviewData["systemHealth"] }) {
  return (
    <Card>
      <CardTitle>System health</CardTitle>
      <ul className="mt-4 space-y-2">
        {items.map((item) => (
          <li key={item.name} className="flex items-center justify-between gap-3 rounded-lg border border-jp-border px-3 py-2 text-sm">
            <span>{item.name}</span>
            <span className="font-medium uppercase tracking-wide text-jp-muted">{item.status}</span>
          </li>
        ))}
      </ul>
    </Card>
  );
}
