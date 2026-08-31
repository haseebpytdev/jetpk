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
 * JP-UI-FAB-01: unified bottom-right floating action dock for sub-xl viewports.
 * Uses native <details> so open/close works even if client hydration is delayed.
 */
export function PublicFloatingActionDock({
  session,
  aiEnabled = false,
}: PublicFloatingActionDockProps) {
  const panelId = useId();
  const detailsRef = useRef<HTMLDetailsElement>(null);
  const signedIn = session.status === "authenticated";
  const pathname = usePathname() ?? "";
  const liftForCheckoutSticky =
    pathname.startsWith("/booking/") || pathname.startsWith("/groups/booking/");
  // Keep FAB clear of result-card Book Now / share controls (bottom-right CTAs).
  const clearPrimaryCtaCorner =
    pathname.startsWith("/flights/results") ||
    pathname.startsWith("/flights/return") ||
    pathname.startsWith("/flights/details");

  useEffect(() => {
    const details = detailsRef.current;
    if (!details) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape" && details.open) {
        details.open = false;
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
        "pointer-events-none fixed z-50 xl:hidden",
        clearPrimaryCtaCorner
          ? "left-[max(1rem,env(safe-area-inset-left))] right-auto"
          : "right-[max(1rem,env(safe-area-inset-right))]",
        liftForCheckoutSticky
          ? "bottom-[max(5.25rem,calc(env(safe-area-inset-bottom)+4.25rem))]"
          : "bottom-[max(1rem,env(safe-area-inset-bottom))]",
      )}
      data-testid="public-fab-dock"
      data-lift-checkout={liftForCheckoutSticky ? "1" : "0"}
      data-clear-cta-corner={clearPrimaryCtaCorner ? "1" : "0"}
      onToggle={(event) => {
        if (event.currentTarget.open) {
          event.currentTarget.focus();
        }
      }}
      onKeyDown={(event) => {
        if (event.key === "Escape") {
          event.currentTarget.open = false;
        }
      }}
    >
      <summary
        className={cn(
          "pointer-events-auto list-none inline-flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-jp-brand text-sm font-bold text-white shadow-jp-md",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
          "[&::-webkit-details-marker]:hidden",
        )}
        aria-controls={panelId}
        aria-label="JetPakistan quick actions"
        data-testid="public-fab-trigger"
      >
        JP
      </summary>

      <div
        id={panelId}
        role="group"
        aria-label="JetPakistan quick actions"
        className={cn(
          "pointer-events-auto absolute bottom-16 mb-1 w-[min(18rem,calc(100vw-2rem))] rounded-jp-lg border border-jp-border bg-jp-surface p-2 shadow-jp-md",
          clearPrimaryCtaCorner ? "left-0 right-auto" : "right-0",
        )}
      >
        <p className="px-2 pb-1 text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Explore</p>
        <ul className="max-h-[min(60vh,22rem)] space-y-1 overflow-y-auto">
          {tiles.map((tile) => (
            <li key={`${tile.label}-${tile.href}`}>
              <Link
                href={tile.href}
                data-testid={tile.testId}
                onClick={() => {
                  if (detailsRef.current) detailsRef.current.open = false;
                }}
                className="flex min-h-11 items-center rounded-jp-md px-3 py-2 text-jp-sm font-semibold text-jp-text hover:bg-jp-brand-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
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
