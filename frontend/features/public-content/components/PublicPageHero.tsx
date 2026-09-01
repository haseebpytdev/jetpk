import { cn } from "@/lib/cn";
import type { PublicPageHero as HeroType } from "../types";

type PublicPageHeroProps = {
  hero: HeroType;
  id?: string;
  className?: string;
  children?: React.ReactNode;
  /** Quieter, task-focused treatment for checkout-adjacent or dense pages. */
  variant?: "default" | "compact" | "support";
};

/**
 * Shared compact public page header — visual depth without giant marketing heroes.
 */
export function PublicPageHero({
  hero,
  id = "page-hero-heading",
  className,
  children,
  variant = "default",
}: PublicPageHeroProps) {
  const compact = variant === "compact";
  const support = variant === "support";

  return (
    <header
      className={cn(
        "jp-page-hero relative overflow-hidden rounded-jp-xl border border-jp-border shadow-jp-card",
        compact ? "p-jp-lg sm:p-jp-xl" : "p-jp-xl sm:p-jp-2xl",
        support
          ? "bg-gradient-to-br from-jp-page via-white to-jp-primary-soft/40"
          : "bg-gradient-to-br from-[#edf7f2] via-white to-jp-page",
        className,
      )}
      data-testid="public-page-hero"
      data-hero-variant={variant}
    >
      <div
        className="pointer-events-none absolute inset-0 opacity-70"
        aria-hidden="true"
        style={{
          backgroundImage:
            "radial-gradient(circle at 92% 12%, rgba(15, 118, 110, 0.14), transparent 38%), radial-gradient(circle at 8% 88%, rgba(15, 118, 110, 0.08), transparent 32%)",
        }}
      />
      <svg
        className="pointer-events-none absolute -right-2 top-6 hidden h-24 w-40 text-jp-primary/20 sm:block"
        viewBox="0 0 160 96"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="M8 72c28-4 46-28 72-36 18-6 36-4 56 10"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeDasharray="3 5"
          strokeLinecap="round"
        />
        <circle cx="140" cy="44" r="3" fill="currentColor" />
      </svg>
      <div className="relative max-w-3xl">
        {hero.kicker ? (
          <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">{hero.kicker}</p>
        ) : null}
        <h1 id={id} className={cn("font-display font-bold text-jp-text", compact ? "mt-2 text-jp-h3" : "mt-3 text-jp-h2")}>
          {hero.title}
        </h1>
        {hero.description ? (
          <p className={cn("leading-relaxed text-jp-muted", compact ? "mt-2 text-jp-sm" : "mt-4 text-jp-body")}>
            {hero.description}
          </p>
        ) : null}
        {children}
      </div>
    </header>
  );
}
