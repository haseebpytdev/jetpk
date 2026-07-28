"use client";

import { useDashboardPortal } from "@/lib/portal-context";
import { dashboardHref } from "@/lib/portal-path";

export function useDashboardPath(path = ""): string {
  const portal = useDashboardPortal();
  return dashboardHref(portal, path);
}
