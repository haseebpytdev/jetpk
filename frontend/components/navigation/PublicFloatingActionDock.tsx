"use client";

import { ThemeSwitch } from "@/components/theme/ThemeSwitch";
import { primaryNavigationForSession } from "@/lib/navigation";
import { cn } from "@/lib/cn";
import type { PublicSession } from "@/types/session";
import Link from "next/link";
import { useEffect, useId, useRef } from "react";
import { usePathname } from "next/navigation";

type PublicFloatingActionDockProps = {
  session: PublicSession;
  aiEnabled?: boolean;
};

/**
 * JP-UI-FAB-01: unified bottom-right floating action dock for sub-lg viewports.
 * Uses native <details> so open/close works even if client hydration is delayed.
 */
export function PublicFloatingActionDock({
  session,
  aiEnabled = false,
}: PublicFloatingActionDockProps) {
  const panelId = useId();
  const detailsRef = useRef<HTMLDetailsElement>(null);
  const summaryRef = useRef<HTMLElement>(null);
  const signedIn = session.status === "authenticated";
  const pathname = usePathname() ?? "";
  const liftForCheckoutSticky =
    pathname.startsWith("/booking/") || pathname.startsWith("/groups/booking/");
  // Elevate FAB above sticky fare Continue / result CTAs (keep bottom-right).
  const liftForFlightCta =
    pathname.startsWith("/flights/results") ||
    pathname.startsWith("/flights/return") ||
    pathname.startsWith("/flights/details");
  const liftFab = liftForCheckoutSticky || liftForFlightCta;

  useEffect(() => {
    const details = detailsRef.current;
    if (!details) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape" && details.open) {
        details.open = false;
        summaryRef.current?.focus();
      }
    };
    const onDocClick = (event: MouseEvent) => {
      if (!details.open) return;
      if (!details.contains(event.target as Node)) {
        details.open = false;
      }
    };

    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("click", onDocClick);
    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.removeEventListener("click", onDocClick);
    };
  }, []);

  const navItems = primaryNavigationForSession(session);
  const tiles: { href: string; label: string; testId?: string }[] = [];

  for (const item of navItems) {
    if (item.type === "link") {
      tiles.push({ href: item.href, label: item.label });
    } else {
      for (const child of item.items) {
        tiles.push({ href: child.href, label: child.label });
      }
    }
  }

  tiles.push({ href: "/support", label: "Support" });
  if (aiEnabled) {
    tiles.push({ href: "/#ask-jetpakistan", label: "Ask JetPakistan", testId: "fab-ask-ai" });
  }
  tiles.push({
    href: signedIn ? (session.dashboardUrl || "/customer/dashboard") : "/login",
    label: signedIn ? "Account" : "Login",
    testId: "fab-account",
  });

  return (
    <details
      ref={detailsRef}
      tabIndex={-1}
      className={cn(
        "jp-public-fab-dock group pointer-events-none fixed right-[max(1rem,env(safe-area-inset-right))] z-50 lg:hidden",
        liftFab
          ? "bottom-[max(6.75rem,calc(env(safe-area-inset-bottom)+5.5rem))]"
          : "bottom-[max(1rem,env(safe-area-inset-bottom))]",
      )}
      data-testid="public-fab-dock"
      data-lift-checkout={liftForCheckoutSticky ? "1" : "0"}
      data-lift-flight-cta={liftForFlightCta ? "1" : "0"}
      onToggle={(event) => {
        if (event.currentTarget.open) {
          event.currentTarget.focus();
        }
      }}
      onKeyDown={(event) => {
        if (event.key === "Escape") {
          event.currentTarget.open = false;
          summaryRef.current?.focus();
        }
      }}
    >
      <summary
        ref={summaryRef}
        className={cn(
          "pointer-events-auto list-none inline-flex h-14 w-14 cursor-pointer items-center justify-center rounded-[1.125rem] border border-white/30 bg-jp-brand text-white shadow-jp-md",
          "transition-[transform,background-color,box-shadow] duration-jp-fast hover:-translate-y-0.5 hover:bg-jp-brand-hover hover:shadow-jp-raised active:translate-y-0 group-open:bg-jp-brand-active",
          "motion-reduce:transform-none motion-reduce:transition-none",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
          "[&::-webkit-details-marker]:hidden",
        )}
        aria-controls={panelId}
        aria-label="JetPakistan quick actions"
        data-testid="public-fab-trigger"
      >
        <span className="inline-flex group-open:hidden" aria-hidden="true">
          <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none">
            <path d="M5 7h14M5 12h14M5 17h14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
          </svg>
        </span>
        <span className="hidden group-open:inline-flex" aria-hidden="true">
          <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none">
            <path d="m6.5 6.5 11 11m0-11-11 11" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
          </svg>
        </span>
      </summary>

      <div
        id={panelId}
        role="group"
        aria-label="JetPakistan quick actions"
        className="jp-public-fab-panel pointer-events-auto absolute bottom-16 right-0 mb-2 w-[min(18rem,calc(100vw-2rem))] origin-bottom-right rounded-jp-xl border border-jp-brand-border bg-jp-surface p-3 shadow-jp-raised"
      >
        <p className="px-2 pb-2 text-jp-xs font-semibold uppercase tracking-[0.14em] text-jp-muted">Quick actions</p>
        <ul className="max-h-[min(60vh,22rem)] space-y-1 overflow-y-auto">
          {tiles.map((tile) => (
            <li key={`${tile.label}-${tile.href}`}>
              <Link
                href={tile.href}
                prefetch
                data-testid={tile.testId}
                onClick={() => {
                  if (detailsRef.current) detailsRef.current.open = false;
                }}
                className="flex min-h-12 items-center rounded-jp-md px-3 py-2 text-jp-sm font-semibold text-jp-text transition-colors duration-jp-fast hover:bg-jp-brand-soft hover:text-jp-brand focus-visible:outline-none focus-visible:shadow-jp-focus motion-reduce:transition-none"
              >
                {tile.label}
              </Link>
            </li>
          ))}
        </ul>
        <div className="mt-2 border-t border-jp-border px-2 pt-2">
          <ThemeSwitch />
        </div>
      </div>
    </details>
  );
}
