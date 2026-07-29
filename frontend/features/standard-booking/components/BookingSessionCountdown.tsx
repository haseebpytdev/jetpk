"use client";

import { useEffect, useMemo, useState } from "react";
import { cn } from "@/lib/cn";

type BookingSessionCountdownProps = {
  expiresAt?: string | null;
  serverTime: string;
  onExpired?: () => void;
  className?: string;
};

export function BookingSessionCountdown({
  expiresAt,
  serverTime,
  onExpired,
  className,
}: BookingSessionCountdownProps) {
  const [remaining, setRemaining] = useState<number | null>(null);

  const offsetMs = useMemo(() => {
    if (!expiresAt) return null;
    const serverMs = new Date(serverTime).getTime();
    const expiresMs = new Date(expiresAt).getTime();
    if (!Number.isFinite(serverMs) || !Number.isFinite(expiresMs)) return null;
    return expiresMs - serverMs;
  }, [expiresAt, serverTime]);

  useEffect(() => {
    if (offsetMs === null) {
      setRemaining(null);
      return;
    }
    const started = Date.now();
    const tick = () => {
      const elapsed = Date.now() - started;
      const left = Math.max(0, Math.floor((offsetMs - elapsed) / 1000));
      setRemaining(left);
      if (left <= 0) onExpired?.();
    };
    tick();
    const id = window.setInterval(tick, 1000);
    return () => window.clearInterval(id);
  }, [offsetMs, onExpired]);

  if (remaining === null) return null;

  const minutes = Math.floor(remaining / 60);
  const seconds = remaining % 60;

  return (
    <p
      className={cn("text-jp-sm text-jp-muted", className)}
      data-testid="booking-session-countdown"
      aria-live="polite"
    >
      {remaining > 0
        ? `Complete within ${minutes}:${seconds.toString().padStart(2, "0")}`
        : "Session expired"}
    </p>
  );
}
