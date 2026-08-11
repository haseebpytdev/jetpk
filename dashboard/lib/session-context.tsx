"use client";

import { createContext, useContext, type ReactNode } from "react";
import type { DashboardSessionSummary, DashboardNavItem, DashboardNavGroup } from "@/services/session-service";

const SessionContext = createContext<DashboardSessionSummary | null>(null);

export function SessionProvider({
  session,
  children,
}: {
  session: DashboardSessionSummary | null;
  children: ReactNode;
}) {
  return <SessionContext.Provider value={session}>{children}</SessionContext.Provider>;
}

export function useDashboardSession(): DashboardSessionSummary | null {
  return useContext(SessionContext);
}

export function useDashboardCapabilities(): Record<string, boolean> {
  const session = useDashboardSession();
  return session?.capabilities ?? {};
}

export function useDashboardNavigation(): DashboardNavItem[] {
  const session = useDashboardSession();
  return session?.navigation ?? [];
}

export function useDashboardNavigationGroups(): DashboardNavGroup[] {
  const session = useDashboardSession();
  if (session?.navigationGroups && session.navigationGroups.length > 0) {
    return session.navigationGroups;
  }
  if (session?.navigation && session.navigation.length > 0) {
    return [{ label: "Navigation", items: session.navigation }];
  }
  return [];
}
