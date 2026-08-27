import Image from "next/image";
import Link from "next/link";
import { cn } from "@/lib/cn";
import type { GroupPackage } from "../types";

type GroupResultCardProps = {
  card: GroupPackage;
  className?: string;
};

/**
 * Compact group result card — flight-inspired zones, shorter height.
 * Primary action: View details (Book Now lives on detail).
 */
export function GroupResultCard({ card, className }: GroupResultCardProps) {
  const packageId = card.public_id ?? String(card.id ?? "");
  const detailHref = `/groups/${encodeURIComponent(packageId)}`;
  const priceLabel = `${card.currency} ${card.price_formatted}`;
  const unavailable = card.cta_disabled || !card.bookable || card.available_seats <= 0;

  return (
    <article
      className={cn(
        "rounded-jp-lg border border-jp-border bg-jp-surface px-4 py-3 shadow-jp-sm",
        className,
      )}
      data-testid="group-result-card"
    >
      <div className="grid gap-3 lg:grid-cols-[minmax(0,11rem)_minmax(0,1fr)_minmax(8.5rem,auto)] lg:items-center">
        <div className="flex min-w-0 items-center gap-3">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-jp-md border border-jp-border bg-white">
            {card.airline_logo_url ? (
              <Image
                src={card.airline_logo_url}
                alt=""
                width={48}
                height={48}
                className="h-10 w-10 object-contain"
              />
            ) : (
              <span className="text-jp-xs font-bold text-jp-muted">
                {card.airline_code ?? card.airline_name.slice(0, 2).toUpperCase()}
              </span>
            )}
          </div>
          <div className="min-w-0">
            <h2 className="truncate text-jp-sm font-semibold text-jp-text">{card.airline_name}</h2>
            {card.airline_code ? <p className="text-jp-xs text-jp-muted">{card.airline_code}</p> : null}
          </div>
        </div>

        <div className="min-w-0 space-y-1">
          <p className="text-jp-sm font-medium text-jp-text">{card.route_line}</p>
          <p className="text-jp-xs text-jp-muted">
            {card.departure_datetime_display ?? card.departure_date_short ?? "Departure TBA"}
          </p>
          <div className="flex flex-wrap items-center gap-2">
            {card.baggage_line ? (
              <span className="text-jp-xs text-jp-muted">{card.baggage_line}</span>
            ) : null}
            <span
              className={cn(
                "inline-flex rounded-jp-pill px-2 py-0.5 text-jp-xs font-medium",
                card.seats_badge_variant === "warn"
                  ? "bg-amber-50 text-amber-900"
                  : "bg-jp-primary-soft text-jp-primary",
              )}
              data-testid="group-available-seats"
            >
              {card.seat_label}
            </span>
          </div>
        </div>

        <div className="flex shrink-0 flex-col items-stretch gap-1.5 sm:items-end">
          <p className="text-right text-jp-base font-semibold text-jp-text" data-testid="group-result-price">
            {priceLabel}
          </p>
          <p className="text-right text-jp-xs text-jp-muted">Per seat</p>
          {unavailable ? (
            <span className="inline-flex min-h-[2.5rem] items-center justify-center rounded-jp-md border border-jp-border bg-jp-surface-muted px-3 text-jp-sm font-semibold text-jp-muted">
              {card.cta_label ?? "Unavailable"}
            </span>
          ) : (
            <Link
              href={detailHref}
              className="inline-flex min-h-[2.5rem] items-center justify-center rounded-jp-md bg-jp-primary px-4 text-jp-sm font-semibold text-white hover:bg-jp-primary-hover focus-visible:outline-none focus-visible:shadow-jp-focus"
              data-testid="group-result-select"
            >
              View details
            </Link>
          )}
        </div>
      </div>
      {card.cta_message ? <p className="mt-2 text-jp-sm text-amber-800">{card.cta_message}</p> : null}
    </article>
  );
}
