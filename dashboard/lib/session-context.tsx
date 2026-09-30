"use client";

import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import type { DashboardPortal } from "@/lib/portal-path";
import { getDashboardSession, type DashboardSessionSummary } from "@/services/session-service";

const SessionContext = createContext<DashboardSessionSummary | null>(null);

function portalFromPath(pathname: string): DashboardPortal {
  if (pathname.startsWith("/staff/") || pathname.includes("/staff/dashboard")) {
    return "staff";
  }
  return "admin";
}

export function SessionProvider({
  session: initialSession,
  children,
}: {
  session: DashboardSessionSummary | null;
  children: ReactNode;
}) {
  const [session, setSession] = useState<DashboardSessionSummary | null>(initialSession);

  useEffect(() => {
    let cancelled = false;
    const portal = portalFromPath(window.location.pathname);
    const needsHydrate =
      !initialSession ||
      initialSession.unavailable ||
      initialSession.sessionUsable === false ||
      initialSession.portalType !== portal;

    if (!needsHydrate) {
      return;
    }

    void getDashboardSession({ portal })
      .then((next) => {
        if (!cancelled && next.sessionUsable !== false && !next.unavailable && next.portalType === portal) {
          setSession(next);
        }
      })
      .catch(() => {
        /* keep prior state */
      });

    return () => {
      cancelled = true;
    };
  }, [initialSession]);

  return <SessionContext.Provider value={session}>{children}</SessionContext.Provider>;
}

export function useDashboardSession(): DashboardSessionSummary | null {
  return useContext(SessionContext);
}

export function useDashboardCapabilities(): Record<string, boolean> {
  const session = useDashboardSession();
  return session?.capabilities ?? {};
}

export function useDashboardNavigation(): Array<{ label: string; href: string; key: string }> {
  const session = useDashboardSession();
  return session?.navigation ?? [];
}
