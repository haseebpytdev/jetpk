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
  useLayoutEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

type PublicFloatingLayoutContextValue = {
  aiEnabled: boolean;
  askOpen: boolean;
  dockOpen: boolean;
  viewportIsSubLg: boolean;
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

const SUB_LG_QUERY = "(max-width: 1023px)";

function readViewportIsSubLg(): boolean {
  if (typeof window === "undefined" || typeof window.matchMedia !== "function") {
    return true;
  }
  return window.matchMedia(SUB_LG_QUERY).matches;
}

export function PublicFloatingLayoutProvider({
  children,
  aiEnabled,
}: PublicFloatingLayoutProviderProps) {
  const pathname = usePathname() ?? "";
  const [askOpen, setAskOpen] = useState(false);
  const [dockOpen, setDockOpen] = useState(false);
  const [viewportIsSubLg, setViewportIsSubLg] = useState(true);

  const liftCheckout =
    pathname.startsWith("/booking/") || pathname.startsWith("/groups/booking/");
  const liftFlightCta =
    pathname.startsWith("/flights/results") ||
    pathname.startsWith("/flights/return") ||
    pathname.startsWith("/flights/details");

  useLayoutEffect(() => {
    const sync = () => setViewportIsSubLg(readViewportIsSubLg());
    sync();
    const mq = window.matchMedia(SUB_LG_QUERY);
    mq.addEventListener("change", sync);
    return () => mq.removeEventListener("change", sync);
  }, []);

  const layoutState = useMemo<PublicFloatingLayoutState>(
    () => ({
      aiEnabled,
      askOpen,
      dockOpen,
      liftCheckout,
      liftFlightCta,
      viewportIsSubLg,
    }),
    [aiEnabled, askOpen, dockOpen, liftCheckout, liftFlightCta, viewportIsSubLg],
  );

  useLayoutEffect(() => {
    applyPublicFloatingLayout(layoutState);
  }, [layoutState]);

  const setAskOpenStable = useCallback((open: boolean) => {
    setAskOpen(open);
    if (open) {
      setDockOpen(false);
    }
  }, []);

  const setDockOpenStable = useCallback((open: boolean) => {
    setDockOpen(open);
    if (open) {
      setAskOpen(false);
    }
  }, []);

  const value = useMemo(
    () => ({
      aiEnabled,
      askOpen,
      dockOpen,
      viewportIsSubLg,
      setAskOpen: setAskOpenStable,
      setDockOpen: setDockOpenStable,
    }),
    [aiEnabled, askOpen, dockOpen, viewportIsSubLg, setAskOpenStable, setDockOpenStable],
  );

  return (
    <PublicFloatingLayoutContext.Provider value={value}>
      {children}
    </PublicFloatingLayoutContext.Provider>
  );
}
