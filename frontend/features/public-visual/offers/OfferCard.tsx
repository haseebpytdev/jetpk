import Link from "next/link";
import { cn } from "@/lib/cn";
import type { HomepageOfferCard } from "../types/homepage";
import { AssetSlot } from "../components/AssetSlot";

type OfferCardProps = {
  offer: HomepageOfferCard;
  compact?: boolean;
};

const THEME_CLASSES: Record<NonNullable<HomepageOfferCard["theme"]>, string> = {
  summer: "from-[#1a6b45] via-[#2d8a55] to-[#4ab87a]",
  weekend: "from-[#0b1d2a] via-[#1a3a52] to-[#2d5a7a]",
  family: "from-[#e8f5ea] via-[#d4edd8] to-[#c5e6cb] text-jp-text",
};

export function OfferCard({ offer, compact = false }: OfferCardProps) {
  const isLight = offer.theme === "family";
  const gradient = offer.theme ? THEME_CLASSES[offer.theme] : THEME_CLASSES.summer;

  return (
    <article
      className={cn(
        "relative flex flex-col justify-between overflow-hidden rounded-xl border border-jp-border shadow-jp-sm",
        compact ? "min-h-[3.5rem] p-1.5" : "min-h-[9rem] p-3",
        isLight ? "bg-gradient-to-br text-jp-text" : "bg-gradient-to-br text-white",
        gradient,
      )}
    >
      <div className="relative z-10 max-w-[70%]">
        {offer.discountCaption ? (
          <p className={cn("font-semibold uppercase tracking-wider", compact ? "text-[9px]" : "text-[10px]", isLight ? "text-jp-muted" : "text-white/80")}>
            {offer.discountCaption}
          </p>
        ) : null}
        {offer.discountValue ? (
          <p className={cn("font-display font-bold leading-tight", compact ? "text-lg" : "text-2xl")}>{offer.discountValue}</p>
        ) : null}
        <h3 className={cn("font-display font-bold", compact ? "text-[11px] leading-tight" : "mt-1 text-jp-sm")}>{offer.title}</h3>
        {offer.subtitle && !compact ? (
          <p className={cn("mt-1 text-[11px] leading-snug", isLight ? "text-jp-muted" : "text-white/85")}>
            {offer.subtitle}
          </p>
        ) : null}
      </div>

      <div className={cn("relative z-10", compact ? "mt-1" : "mt-3")}>
        <Link
          href={offer.ctaHref}
          className={cn(
            "inline-flex rounded-lg font-semibold transition-colors focus-visible:outline-none focus-visible:shadow-jp-focus",
            compact ? "px-2.5 py-1 text-[10px]" : "px-4 py-2 text-jp-xs",
            isLight
              ? "bg-jp-primary text-white hover:bg-jp-primary-hover"
              : "bg-white text-jp-primary hover:bg-white/90",
          )}
        >
          {offer.ctaLabel}
        </Link>
      </div>

      <div className={cn("pointer-events-none absolute bottom-0 right-0 opacity-30", compact ? "h-14 w-14" : "h-24 w-24")}>
        <AssetSlot
          src={offer.image}
          alt={offer.imageAlt ?? offer.title}
          width={96}
          height={96}
          variant="card-neutral"
          objectFit="contain"
        />
      </div>
    </article>
  );
}
