"use client";

import { useRouter } from "next/navigation";
import { useDashboardPortal } from "@/lib/portal-context";
import { dashboardHref } from "@/lib/portal-path";

export function useDashboardHref(path = ""): string {
  const portal = useDashboardPortal();
  return dashboardHref(portal, path);
}

export function useDashboardRouter() {
  const router = useRouter();
  const portal = useDashboardPortal();

  return {
    push(path: string) {
      router.push(dashboardHref(portal, path));
    },
    replace(path: string) {
      router.replace(dashboardHref(portal, path));
    },
    refresh() {
      router.refresh();
    },
  };
}
