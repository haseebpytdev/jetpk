"use client";

import { useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { postLaravelLogout } from "@/lib/laravel-auth-api";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import type { DashboardSessionSummary } from "@/services/session-service";

type Props = {
  onMenuClick: () => void;
  session?: DashboardSessionSummary | null;
};

export function DashboardHeader({ onMenuClick, session }: Props) {
  const isLive = useDashboardLiveMode();
  const profile = session ?? null;
  const [profileOpen, setProfileOpen] = useState(false);
  const [fullscreen, setFullscreen] = useState(false);
  const [logoutPending, setLogoutPending] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onFs = () => setFullscreen(Boolean(document.fullscreenElement));
    document.addEventListener("fullscreenchange", onFs);
    return () => document.removeEventListener("fullscreenchange", onFs);
  }, []);

  useEffect(() => {
    if (!profileOpen) {
      return undefined;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setProfileOpen(false);
      }
    };

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [profileOpen]);

  const toggleFullscreen = async () => {
    try {
      if (document.fullscreenElement) {
        await document.exitFullscreen();
      } else {
        await document.documentElement.requestFullscreen();
      }
    } catch {
      /* ignore */
    }
  };

  const handleLogout = async () => {
    if (!isLive || logoutPending) {
      return;
    }

    setLogoutPending(true);
    const result = await postLaravelLogout();
    if (result.ok) {
      window.location.assign(result.redirect);
      return;
    }

    setLogoutPending(false);
    alert(result.message);
  };

  return (
    <header className="sticky top-0 z-30 flex min-h-[4.5rem] flex-wrap items-center gap-3 border-b border-jp-border bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
      <Button variant="ghost" size="sm" className="lg:hidden" onClick={onMenuClick} aria-label="Open navigation menu">
        ☰
      </Button>
      <div className="relative hidden min-w-[200px] flex-1 md:block md:max-w-xl">
        <label className="sr-only" htmlFor="global-search">
          Quick search
        </label>
        <input
          id="global-search"
          type="search"
          disabled={!isLive}
          placeholder="Search bookings, PNR, customers, agents…"
          className="w-full rounded-xl border border-jp-border bg-gray-50 px-4 py-2.5 text-sm text-gray-500 disabled:cursor-not-allowed"
          title={isLive ? "Global search" : "Sign in to search"}
        />
        <kbd className="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 rounded border bg-white px-1.5 text-[10px] text-gray-500 sm:inline">
          Ctrl+K
        </kbd>
      </div>
      <div className="ml-auto flex flex-wrap items-center gap-2">
        <button
          type="button"
          className="flex min-h-11 items-center gap-2 rounded-xl border border-jp-border px-3 text-sm text-gray-700"
          title="Display currency"
          aria-label="Currency PKR"
        >
          <span aria-hidden>🇵🇰</span> PKR
        </button>
        <IconButton label={fullscreen ? "Exit fullscreen" : "Enter fullscreen"} onClick={toggleFullscreen} />
        <div className="relative" ref={menuRef}>
          <button
            type="button"
            className="flex min-h-11 items-center gap-2 rounded-xl border border-jp-border px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            aria-expanded={profileOpen}
            aria-haspopup="menu"
            onClick={() => setProfileOpen((value) => !value)}
          >
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-jp-accent/15 text-xs font-semibold text-jp-accent-muted">
              {profile?.initials ?? "??"}
            </span>
            <span className="hidden max-w-[120px] truncate sm:inline">
              {profile?.displayName ?? (isLive ? "Session unavailable" : "Preview user")}
            </span>
          </button>
          {profileOpen ? (
            <div
              role="menu"
              className="absolute right-0 mt-2 w-56 rounded-xl border border-jp-border bg-white py-2 shadow-lg"
            >
              <div className="border-b px-4 pb-2">
                <p className="text-sm font-semibold">{profile?.displayName ?? "Signed out"}</p>
                <p className="text-xs text-jp-muted">{profile?.email ?? "—"}</p>
                {profile?.roles?.[0] ? <p className="mt-1 text-xs text-jp-muted">{profile.roles[0]}</p> : null}
              </div>
              <button
                type="button"
                className="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50 disabled:text-gray-400"
                role="menuitem"
                disabled={!isLive || logoutPending}
                onClick={handleLogout}
              >
                {logoutPending ? "Signing out…" : "Log out"}
              </button>
            </div>
          ) : null}
        </div>
      </div>
    </header>
  );
}

function IconButton({
  label,
  badge,
  onClick,
}: {
  label: string;
  badge?: number;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      className="relative flex h-11 w-11 items-center justify-center rounded-xl border border-jp-border text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
    >
      <span aria-hidden className="text-lg">
        ○
      </span>
      {badge ? (
        <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-jp-accent px-1 text-[10px] font-bold text-white">
          {badge}
        </span>
      ) : null}
    </button>
  );
}
