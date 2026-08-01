"use client";

import { cn } from "@/lib/cn";
import { useEffect, useRef, useState } from "react";
import { usePathname } from "next/navigation";

const FAILSAFE_MS = 12000;
const START_DELAY_MS = 80;

function isInternalNavigation(anchor: HTMLAnchorElement): boolean {
  if (anchor.target === "_blank") return false;
  if (anchor.hasAttribute("download")) return false;
  if (anchor.dataset.noRouteProgress !== undefined) return false;
  if (anchor.dataset.secureHandoff !== undefined) return false;

  const href = anchor.getAttribute("href");
  if (!href || href.startsWith("#") || href.startsWith("mailto:") || href.startsWith("tel:")) {
    return false;
  }

  try {
    const url = new URL(href, window.location.origin);
    if (url.origin !== window.location.origin) return false;
    if (url.pathname.startsWith("/laravel/")) return false;
    return true;
  } catch {
    return false;
  }
}

export function RouteNavProgress() {
  const pathname = usePathname();
  const [active, setActive] = useState(false);
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const failsafeRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  const stop = () => {
    if (timerRef.current) clearTimeout(timerRef.current);
    if (failsafeRef.current) clearTimeout(failsafeRef.current);
    setActive(false);
  };

  const start = () => {
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => {
      setActive(true);
      if (failsafeRef.current) clearTimeout(failsafeRef.current);
      failsafeRef.current = setTimeout(stop, FAILSAFE_MS);
    }, START_DELAY_MS);
  };

  useEffect(() => {
    stop();
  }, [pathname]);

  useEffect(() => {
    const onClick = (event: MouseEvent) => {
      if (event.defaultPrevented) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

      const target = event.target as HTMLElement | null;
      const anchor = target?.closest("a");
      if (!anchor || !isInternalNavigation(anchor)) return;

      start();
    };

    document.addEventListener("click", onClick, true);
    return () => {
      document.removeEventListener("click", onClick, true);
      stop();
    };
  }, []);

  if (!active) return null;

  return (
    <>
      <div
        className="jp-route-progress"
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label="Page loading"
        data-testid="route-nav-progress"
      />
      <div className="sr-only" aria-live="polite" aria-atomic="true">
        Loading page
      </div>
    </>
  );
}

export function RouteNavProgressAnnouncer({ className }: { className?: string }) {
  return <div className={cn("sr-only", className)} aria-live="polite" id="route-nav-announcer" />;
}
