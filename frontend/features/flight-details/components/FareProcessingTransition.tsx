"use client";

export type FareProcessingPhase =
  | "VALIDATING_FARE"
  | "PREPARING_TRAVELER"
  | "NAVIGATING_TO_TRAVELER";

const PHASE_COPY: Record<
  FareProcessingPhase,
  { headline: string; support: string; testId: string }
> = {
  VALIDATING_FARE: {
    headline: "Checking the latest fare and availability",
    support: "We're confirming your selected flight with the airline.",
    testId: "fare-processing-validating",
  },
  PREPARING_TRAVELER: {
    headline: "Preparing traveler details",
    support: "Your fare is confirmed. We're getting the next step ready.",
    testId: "fare-processing-preparing",
  },
  NAVIGATING_TO_TRAVELER: {
    headline: "Almost there",
    support: "Taking you securely to traveler details.",
    testId: "fare-processing-navigating",
  },
};

type FareProcessingTransitionProps = {
  phase: FareProcessingPhase;
  origin?: string;
  destination?: string;
};

/**
 * JetPakistan-native Book Now → Traveler processing transition.
 * States must match real pipeline phases — never invent progress percentages.
 */
export function FareProcessingTransition({
  phase,
  origin,
  destination,
}: FareProcessingTransitionProps) {
  const copy = PHASE_COPY[phase];
  const routeLabel =
    origin && destination ? `${origin.toUpperCase()} → ${destination.toUpperCase()}` : null;

  return (
    <div
      className="rounded-jp-card border border-jp-border bg-jp-surface p-5 sm:p-6"
      role="status"
      aria-live="polite"
      data-testid="fare-processing-transition"
      data-phase={phase}
    >
      <div className="mx-auto flex max-w-md flex-col items-center text-center">
        <div
          className="relative mb-4 h-16 w-full max-w-[220px]"
          aria-hidden="true"
          data-testid={copy.testId}
        >
          <svg viewBox="0 0 220 64" className="h-full w-full text-jp-primary" fill="none">
            <path
              d="M12 40 C70 12, 150 12, 208 40"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeDasharray="4 6"
              className="opacity-40"
            />
            <circle cx="20" cy="40" r="4" className="fill-jp-primary" />
            <circle cx="200" cy="40" r="4" className="fill-jp-accent" />
            <g className="jp-fare-flight-motif">
              <path
                d="M96 28 L118 36 L96 44 L100 36 Z"
                className="fill-jp-primary"
                transform="rotate(18 107 36)"
              />
            </g>
          </svg>
          <div className="absolute inset-x-8 bottom-0 h-1 overflow-hidden rounded-full bg-jp-border-soft">
            <div className="h-full w-1/2 animate-pulse rounded-full bg-jp-primary/70 motion-reduce:animate-none" />
          </div>
        </div>

        {routeLabel ? (
          <p className="mb-1 text-xs font-medium uppercase tracking-wide text-jp-text-muted">
            {routeLabel}
          </p>
        ) : null}

        <h3 className="text-base font-semibold text-jp-text sm:text-lg">{copy.headline}</h3>
        <p className="mt-1.5 text-sm text-jp-text-muted">{copy.support}</p>
      </div>
    </div>
  );
}
