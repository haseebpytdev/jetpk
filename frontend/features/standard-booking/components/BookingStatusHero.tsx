"use client";

import { useEffect, useState } from "react";
import type { SuccessPresentation } from "../types/review-payment";
import { prefersReducedMotion } from "../utils/status-presentation";

type SuccessConfettiProps = {
  active: boolean;
};

export function SuccessConfetti({ active }: SuccessConfettiProps) {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (!active || prefersReducedMotion()) return undefined;
    setVisible(true);
    const timer = setTimeout(() => setVisible(false), 1800);
    return () => clearTimeout(timer);
  }, [active]);

  if (!visible) return null;

  return (
    <div className="pointer-events-none absolute inset-x-0 top-0 flex justify-center gap-2 overflow-hidden py-4" aria-hidden="true">
      {Array.from({ length: 12 }).map((_, index) => (
        <span
          key={index}
          className="h-2 w-2 animate-bounce rounded-full bg-emerald-500"
          style={{ animationDelay: `${index * 80}ms` }}
        />
      ))}
    </div>
  );
}

type BookingStatusHeroProps = {
  presentation: SuccessPresentation;
  bookingReference?: string | null;
};

export function BookingStatusHero({ presentation, bookingReference }: BookingStatusHeroProps) {
  return (
    <section className="relative overflow-hidden rounded-jp-lg border border-jp-border bg-jp-surface p-6 text-center print:border-0">
      <SuccessConfetti active={presentation.show_celebration} />
      <h1 className="text-2xl font-semibold text-jp-text">{presentation.heading}</h1>
      <p className="mt-2 text-jp-sm text-jp-muted">{presentation.subtitle}</p>
      {bookingReference ? (
        <>
          <p className="mt-4 text-jp-xs uppercase tracking-wide text-jp-muted">Booking reference</p>
          <p className="break-all text-xl font-semibold tracking-wide text-jp-text" data-testid="booking-reference">
            {bookingReference}
          </p>
        </>
      ) : null}
    </section>
  );
}
