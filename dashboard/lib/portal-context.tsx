"use client";

import { createContext, useContext, type ReactNode } from "react";
import type { DashboardPortal } from "@/lib/portal-path";

const PortalContext = createContext<DashboardPortal>("admin");

export function PortalProvider({ portal, children }: { portal: DashboardPortal; children: ReactNode }) {
  return <PortalContext.Provider value={portal}>{children}</PortalContext.Provider>;
}

export function useDashboardPortal(): DashboardPortal {
  return useContext(PortalContext);
}
