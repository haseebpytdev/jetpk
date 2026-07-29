"use client";

import { useEffect, useMemo, useState } from "react";
import { cn } from "@/lib/cn";

type GroupHoldCountdownProps = {
  expiresAt?: string | null;
  serverTime?: string | null;
  warningThresholdSeconds?: number;
  onExpired?: () => void;
  className?: string;
};

function parseTime(value?: string | null): number | null {
  if (!value) return null;
  const parsed = Date.parse(value);
  return Number.isNaN(parsed) ? null : parsed;
}

export function GroupHoldCountdown({
  expiresAt,
  serverTime,
  warningThresholdSeconds = 300,
  onExpired,
  className,
}: GroupHoldCountdownProps) {
  const expiresMs = parseTime(expiresAt);
  const serverOffsetMs = useMemo(() => {
    const serverMs = parseTime(serverTime);
    return serverMs !== null ? serverMs - Date.now() : 0;
  }, [serverTime]);

  const [remainingMs, setRemainingMs] = useState<number | null>(() => {
    if (expiresMs === null) return null;
    return Math.max(0, expiresMs - (Date.now() + serverOffsetMs));
  });

  useEffect(() => {
    if (expiresMs === null) return undefined;

    const tick = () => {
      const next = Math.max(0, expiresMs - (Date.now() + serverOffsetMs));
      setRemainingMs(next);
      if (next <= 0) onExpired?.();
    };

    tick();
    const id = window.setInterval(tick, 1000);
    return () => window.clearInterval(id);
  }, [expiresMs, serverOffsetMs, onExpired]);

  if (expiresMs === null || remainingMs === null) return null;

  const totalSeconds = Math.floor(remainingMs / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  const urgent = totalSeconds > 0 && totalSeconds <= warningThresholdSeconds;
  const expired = totalSeconds <= 0;

  return (
    <div
      role="status"
      aria-live="polite"
      aria-atomic="true"
      className={cn(
        "rounded-jp-md border px-4 py-3",
        expired ? "border-red-200 bg-red-50 text-red-800" : urgent ? "border-amber-200 bg-amber-50 text-amber-900" : "border-jp-border bg-jp-surface",
        className,
      )}
      data-testid="group-hold-countdown"
    >
      <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Reservation timer</p>
      <p className="mt-1 text-lg font-semibold tabular-nums">
        {expired ? "00:00" : `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`}
      </p>
      <p className="mt-1 text-jp-sm">
        {expired ? "Your reservation hold has expired." : "Complete payment before this hold expires."}
      </p>
    </div>
  );
}
