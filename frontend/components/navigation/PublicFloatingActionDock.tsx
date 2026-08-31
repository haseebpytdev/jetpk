"use client";

import { ThemeSwitch } from "@/components/theme/ThemeSwitch";
import { useBodyScrollLock } from "@/lib/hooks/use-body-scroll-lock";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { primaryNavigationForSession } from "@/lib/navigation";
import { cn } from "@/lib/cn";
import type { PublicSession } from "@/types/session";
import Link from "next/link";
import { useCallback, useEffect, useId, useRef, useState } from "react";

type PublicFloatingActionDockProps = {
  session: PublicSession;
  aiEnabled?: boolean;
};

/**
 * JP-UI-FAB-01: unified bottom-right floating action dock for sub-xl viewports.
 * Replaces the problematic mobile drawer that competed with logo/header chrome.
 */
export function PublicFloatingActionDock({
  session,
  aiEnabled = false,
}: PublicFloatingActionDockProps) {
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const signedIn = session.status === "authenticated";

  const close = useCallback(() => setOpen(false), []);
  useEscapeKey(open, close);
  useBodyScrollLock(false);

  useEffect(() => {
    if (!open) return;
    const onPointer = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    // Use click (not mousedown) so the opening click cannot race-close the panel.
    document.addEventListener("click", onPointer);
    return () => document.removeEventListener("click", onPointer);
  }, [open]);

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
    <div
      ref={rootRef}
      className="pointer-events-none fixed bottom-[max(1rem,env(safe-area-inset-bottom))] right-[max(1rem,env(safe-area-inset-right))] z-50 flex flex-col items-end gap-2 xl:hidden"
      data-testid="public-fab-dock"
    >
      {open ? (
        <div
          id={panelId}
          role="dialog"
          aria-label="JetPakistan quick actions"
          className="pointer-events-auto mb-1 w-[min(18rem,calc(100vw-2rem))] rounded-jp-lg border border-jp-border bg-jp-surface p-2 shadow-jp-md motion-safe:animate-in"
        >
          <p className="px-2 pb-1 text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Explore</p>
          <ul className="max-h-[min(60vh,22rem)] space-y-1 overflow-y-auto">
            {tiles.map((tile) => (
              <li key={`${tile.label}-${tile.href}`}>
                <Link
                  href={tile.href}
                  data-testid={tile.testId}
                  onClick={close}
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
      ) : null}

      <button
        type="button"
        className={cn(
          "pointer-events-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-jp-brand text-sm font-bold text-white shadow-jp-md",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
          "motion-safe:transition-transform motion-safe:duration-200",
          open && "rotate-45",
        )}
        aria-expanded={open}
        aria-controls={panelId}
        aria-label={open ? "Close JetPakistan quick actions" : "Open JetPakistan quick actions"}
        data-testid="public-fab-trigger"
        onClick={(event) => {
          event.stopPropagation();
          setOpen((value) => !value);
        }}
      >
        {open ? "×" : "JP"}
      </button>
    </div>
  );
}
