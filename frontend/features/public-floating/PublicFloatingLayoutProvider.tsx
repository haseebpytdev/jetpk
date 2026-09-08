"use client";

import {
  applyPublicFloatingLayout,
  type PublicFloatingLayoutState,
} from "@/features/public-floating/public-floating-layout";
import { usePathname } from "next/navigation";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

type PublicFloatingLayoutContextValue = {
  aiEnabled: boolean;
  askOpen: boolean;
  dockOpen: boolean;
  setAskOpen: (open: boolean) => void;
  setDockOpen: (open: boolean) => void;
};

const PublicFloatingLayoutContext =
  createContext<PublicFloatingLayoutContextValue | null>(null);

export function usePublicFloatingLayout(): PublicFloatingLayoutContextValue {
  const ctx = useContext(PublicFloatingLayoutContext);
  if (!ctx) {
    throw new Error("usePublicFloatingLayout requires PublicFloatingLayoutProvider");
  }
  return ctx;
}

export function usePublicFloatingLayoutOptional(): PublicFloatingLayoutContextValue | null {
  return useContext(PublicFloatingLayoutContext);
}

type PublicFloatingLayoutProviderProps = {
  children: ReactNode;
  aiEnabled: boolean;
};

export function PublicFloatingLayoutProvider({
  children,
  aiEnabled,
}: PublicFloatingLayoutProviderProps) {
  const pathname = usePathname() ?? "";
  const [askOpen, setAskOpen] = useState(false);
  const [dockOpen, setDockOpen] = useState(false);

  const liftCheckout =
    pathname.startsWith("/booking/") || pathname.startsWith("/groups/booking/");
  const liftFlightCta =
    pathname.startsWith("/flights/results") ||
    pathname.startsWith("/flights/return") ||
    pathname.startsWith("/flights/details");

  const layoutState = useMemo<PublicFloatingLayoutState>(
    () => ({
      aiEnabled,
      askOpen,
      dockOpen,
      liftCheckout,
      liftFlightCta,
    }),
    [aiEnabled, askOpen, dockOpen, liftCheckout, liftFlightCta],
  );

  useEffect(() => {
    applyPublicFloatingLayout(layoutState);
  }, [layoutState]);

  const setAskOpenStable = useCallback((open: boolean) => {
    setAskOpen(open);
    if (open) {
      setDockOpen(false);
    }
  }, []);

  const value = useMemo(
    () => ({
      aiEnabled,
      askOpen,
      dockOpen,
      setAskOpen: setAskOpenStable,
      setDockOpen,
    }),
    [aiEnabled, askOpen, dockOpen, setAskOpenStable],
  );

  return (
    <PublicFloatingLayoutContext.Provider value={value}>
      {children}
    </PublicFloatingLayoutContext.Provider>
  );
}
