"use client";

import Link from "next/link";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { laravelRouteUrl } from "@/lib/laravel-route-url";
import type { OverviewData, PipelineStage, SupplierStatusItem } from "@/types/dashboard";

export function BookingPipelinePanel({ stages }: { stages: PipelineStage[] }) {
  if (stages.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardTitle>Booking pipeline</CardTitle>
      <CardDescription className="mt-1">Counts from live booking workflow states.</CardDescription>
      <ul className="mt-4 space-y-2">
        {stages.map((stage) => {
          const href = laravelRouteUrl(stage.laravelRoute, stage.queue ? { queue: stage.queue } : undefined);
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
  if (items.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardTitle>Payment operations</CardTitle>
      <ul className="mt-4 space-y-2">
        {items.map((item) => {
          const href = laravelRouteUrl(item.laravelRoute, item.queue ? { queue: item.queue } : undefined);
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
  if (items.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardTitle>Support workload</CardTitle>
      <ul className="mt-4 space-y-2">
        {items.map((item) => {
          const href = laravelRouteUrl(item.laravelRoute, item.queue ? { queue: item.queue } : undefined);
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
          <li key={item.name} className="flex items-center justify-between text-sm">
            <span>{item.name}</span>
            <span className="flex items-center gap-2 capitalize text-jp-muted">
              <span
                className={`h-2 w-2 rounded-full ${
                  item.status === "operational" ? "bg-emerald-500" : item.status === "degraded" ? "bg-amber-500" : "bg-red-500"
                }`}
                aria-hidden
              />
              {item.status}
            </span>
          </li>
        ))}
      </ul>
    </Card>
  );
}
