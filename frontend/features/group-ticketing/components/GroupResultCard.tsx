import Image from "next/image";
import Link from "next/link";
import { cn } from "@/lib/cn";
import type { GroupPackage } from "../types";

type GroupResultCardProps = {
  card: GroupPackage;
  className?: string;
};

export function GroupResultCard({ card, className }: GroupResultCardProps) {
  const packageId = card.public_id ?? String(card.id ?? "");
  const priceLabel = `${card.currency} ${card.price_formatted}`;
  const disabled = card.cta_disabled || !card.bookable || card.available_seats <= 0;

  return (
    <article
      className={cn(
        "rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm",
        className,
      )}
      data-testid="group-result-card"
    >
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex min-w-0 flex-1 gap-3">
          <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-jp-md border border-jp-border bg-white">
            {card.airline_logo_url ? (
              <Image
                src={card.airline_logo_url}
                alt=""
                width={56}
                height={56}
                className="h-12 w-12 object-contain"
              />
            ) : (
              <span className="text-jp-xs font-bold text-jp-muted">{card.airline_code ?? card.airline_name.slice(0, 2).toUpperCase()}</span>
            )}
          </div>
          <div className="min-w-0">
            <h2 className="text-jp-base font-semibold text-jp-text">{card.airline_name}</h2>
            <p className="text-jp-sm text-jp-text">{card.route_line}</p>
            <p className="text-jp-sm text-jp-muted">{card.departure_datetime_display ?? card.departure_date_short}</p>
            {card.baggage_line ? <p className="mt-1 text-jp-xs text-jp-muted">{card.baggage_line}</p> : null}
            <p
              className={cn(
                "mt-2 inline-flex rounded-jp-pill px-2 py-0.5 text-jp-xs font-medium",
                card.seats_badge_variant === "warn" ? "bg-amber-50 text-amber-900" : "bg-jp-primary-soft text-jp-primary",
              )}
              data-testid="group-available-seats"
            >
              {card.seat_label}
            </p>
          </div>
        </div>

        <div className="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
          {disabled ? (
            <span className="inline-flex min-h-[2.75rem] min-w-[7.5rem] items-center justify-center rounded-jp-md border border-jp-border bg-jp-surface-muted px-4 py-2 text-sm font-semibold text-jp-text-muted">
              {card.cta_label ?? "Unavailable"}
            </span>
          ) : (
            <Link
              href={`/groups/${encodeURIComponent(packageId)}`}
              className="inline-flex min-h-[2.75rem] min-w-[7.5rem] items-center justify-center rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-semibold text-white hover:bg-jp-primary-hover focus-visible:outline-none focus-visible:shadow-jp-focus"
              aria-label={`Select package priced ${priceLabel}`}
              data-testid="group-result-select"
            >
              {priceLabel}
            </Link>
          )}
          <Link
            href={`/groups/${encodeURIComponent(packageId)}`}
            className="text-center text-jp-sm font-medium text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
          >
            View details
          </Link>
        </div>
      </div>
      {card.cta_message ? <p className="mt-3 text-jp-sm text-amber-800">{card.cta_message}</p> : null}
    </article>
  );
}
