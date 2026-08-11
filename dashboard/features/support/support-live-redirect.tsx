"use client";

import type { ReactNode } from "react";
import { LaravelLiveRedirect } from "@/components/dashboard/laravel-live-redirect";
import { useDashboardPortal } from "@/lib/portal-context";

export function SupportLiveRedirect({ children }: { children?: ReactNode }) {
  const portal = useDashboardPortal();
  const route = portal === "staff" ? "staff.support.tickets.index" : "admin.support.tickets.index";

  return (
    <LaravelLiveRedirect route={route} label="Support tickets">
      {children}
    </LaravelLiveRedirect>
  );
}
