import { cn } from "@/lib/cn";

type ResultsHeroBandProps = {
  className?: string;
};

/**
 * Decorative results hero band (mockup #13). Search summary overlaps the lower edge.
 * Does not invent routes, prices, or supplier data.
 */
export function ResultsHeroBand({ className }: ResultsHeroBandProps) {
  return (
    <header
      className={cn("jp-results-hero relative overflow-hidden", className)}
      data-testid="results-hero-band"
      aria-labelledby="results-hero-heading"
    >
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p className="text-jp-xs font-semibold uppercase tracking-[0.18em] text-jp-primary">Flight results</p>
        <h2 id="results-hero-heading" className="mt-jp-xs font-sans text-jp-h2 font-bold text-jp-text sm:text-3xl">
          Choose Your <span className="text-jp-brand">Perfect Flight</span>
        </h2>
        <p className="mt-1.5 max-w-2xl text-sm text-jp-muted">
          Compare available flights, review the fare details, and choose the journey that suits you.
        </p>
      </div>
    </header>
  );
}
