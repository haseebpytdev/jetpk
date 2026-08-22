import { cn } from "@/lib/cn";

type ResultsHeroBandProps = {
  className?: string;
};

/**
 * Cohesive JetPakistan results header — eyebrow, heading, subtitle.
 * Search context card sits below; does not invent routes or prices.
 */
export function ResultsHeroBand({ className }: ResultsHeroBandProps) {
  return (
    <header
      className={cn("jp-results-hero relative overflow-hidden pb-3 pt-5 sm:pb-4 sm:pt-6", className)}
      data-testid="results-hero-band"
      aria-labelledby="results-hero-heading"
    >
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p
          className="text-[11px] font-semibold uppercase tracking-[0.16em] text-jp-primary"
          data-testid="results-hero-eyebrow"
        >
          Flight results
        </p>
        <h2
          id="results-hero-heading"
          className="mt-1 font-sans text-2xl font-bold tracking-tight text-jp-text sm:text-3xl"
        >
          Choose Your <span className="text-jp-primary">Perfect Flight</span>
        </h2>
        <p className="mt-1.5 max-w-2xl text-sm leading-snug text-jp-muted">
          Compare available flights, review fare options, and choose the journey that suits you.
        </p>
      </div>
    </header>
  );
}
