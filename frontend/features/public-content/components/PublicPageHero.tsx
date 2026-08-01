import { cn } from "@/lib/cn";
import type { PublicPageHero as HeroType } from "../types";

type PublicPageHeroProps = {
  hero: HeroType;
  id?: string;
  className?: string;
  children?: React.ReactNode;
};

export function PublicPageHero({ hero, id = "page-hero-heading", className, children }: PublicPageHeroProps) {
  return (
    <header className={cn("jp-page-hero relative overflow-hidden rounded-jp-xl border border-jp-border bg-gradient-to-br from-[#edf7f2] via-white to-jp-page p-jp-2xl shadow-jp-card", className)}>
      <div className="pointer-events-none absolute -right-10 top-0 h-40 w-40 rounded-full bg-jp-primary/10 blur-3xl" aria-hidden="true" />
      <div className="relative max-w-3xl">
        {hero.kicker ? (
          <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">{hero.kicker}</p>
        ) : null}
        <h1 id={id} className="mt-3 font-display text-jp-h2 font-bold text-jp-text">
          {hero.title}
        </h1>
        {hero.description ? <p className="mt-4 text-jp-body leading-relaxed text-jp-muted">{hero.description}</p> : null}
        {children}
      </div>
    </header>
  );
}
