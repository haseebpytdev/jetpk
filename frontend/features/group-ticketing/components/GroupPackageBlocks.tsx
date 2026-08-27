import Image from "next/image";
import type { GroupPackage } from "../types";
import { GroupResultCard } from "./GroupResultCard";

type GroupPackageSummaryProps = {
  package: GroupPackage;
};

export function GroupPackageSummary({ package: pkg }: GroupPackageSummaryProps) {
  return <GroupResultCard card={pkg} />;
}

type GroupPackageHeroProps = {
  package: GroupPackage;
};

export function GroupPackageHero({ package: pkg }: GroupPackageHeroProps) {
  return (
    <header className="space-y-3" data-testid="group-package-hero">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex h-14 w-14 items-center justify-center overflow-hidden rounded-jp-lg border border-jp-border bg-white">
          {pkg.airline_logo_url ? (
            <Image src={pkg.airline_logo_url} alt="" width={56} height={56} className="h-12 w-12 object-contain" />
          ) : (
            <span className="text-jp-sm font-bold text-jp-muted">
              {pkg.airline_code ?? pkg.airline_name.slice(0, 2).toUpperCase()}
            </span>
          )}
        </div>
        <div className="min-w-0">
          <p className="text-jp-xs font-semibold uppercase tracking-[0.14em] text-jp-muted">Selected group</p>
          <h1 className="text-2xl font-semibold tracking-[-0.02em] text-jp-text">{pkg.title}</h1>
          <p className="text-jp-sm font-medium text-jp-text">{pkg.airline_name}</p>
        </div>
      </div>
      <p className="text-jp-base font-medium text-jp-text">{pkg.route_line}</p>
      <div className="flex flex-wrap gap-2">
        {pkg.trip_type_label ? (
          <span className="rounded-jp-pill bg-jp-surface-muted px-2.5 py-1 text-jp-xs font-semibold text-jp-text">
            {pkg.trip_type_label}
          </span>
        ) : null}
        {pkg.category_name ? (
          <span className="rounded-jp-pill bg-jp-primary-soft px-2.5 py-1 text-jp-xs font-semibold text-jp-primary">
            {pkg.category_name}
          </span>
        ) : null}
        {pkg.meal_label ? (
          <span className="rounded-jp-pill border border-jp-border px-2.5 py-1 text-jp-xs font-medium text-jp-muted">
            {pkg.meal_label}
          </span>
        ) : null}
      </div>
    </header>
  );
}

type GroupAvailabilityBadgeProps = {
  availableSeats: number;
  seatLabel: string;
  variant?: "ok" | "warn";
};

export function GroupAvailabilityBadge({ availableSeats, seatLabel, variant = "ok" }: GroupAvailabilityBadgeProps) {
  if (availableSeats <= 0) {
    return <span className="rounded-jp-pill bg-red-50 px-2 py-0.5 text-jp-xs font-medium text-red-800">Unavailable</span>;
  }

  return (
    <span
      className={
        variant === "warn"
          ? "rounded-jp-pill bg-amber-50 px-2 py-0.5 text-jp-xs font-medium text-amber-900"
          : "rounded-jp-pill bg-jp-primary-soft px-2 py-0.5 text-jp-xs font-medium text-jp-primary"
      }
      data-testid="group-availability-badge"
    >
      {seatLabel}
    </span>
  );
}

type GroupPriceBlockProps = {
  currency: string;
  priceFormatted: string;
  totalFormatted?: string;
  seatCount?: number;
  explanation?: string;
  breakdownSource?: "TOTAL_ONLY" | "ITEMIZED";
};

export function GroupPriceBlock({
  currency,
  priceFormatted,
  totalFormatted,
  seatCount,
  explanation = "Per-seat fare",
  breakdownSource = "TOTAL_ONLY",
}: GroupPriceBlockProps) {
  return (
    <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm" data-testid="group-price-block">
      <p className="text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">{explanation}</p>
      <p className="mt-1 text-2xl font-semibold tracking-[-0.03em] text-jp-text">
        {currency} {priceFormatted}
      </p>
      {breakdownSource === "TOTAL_ONLY" ? (
        <p className="mt-1 text-jp-xs text-jp-muted">Total per seat from inventory. No fabricated tax split.</p>
      ) : null}
      {totalFormatted && seatCount ? (
        <p className="mt-2 border-t border-jp-border pt-2 text-jp-sm font-medium text-jp-text">
          Total for {seatCount} seat{seatCount === 1 ? "" : "s"}: {currency} {totalFormatted}
        </p>
      ) : null}
    </div>
  );
}

type GroupBookingSummaryCardProps = {
  package: GroupPackage;
  seatCount?: number;
  totalFormatted?: string;
  className?: string;
};

/** Polished right-rail booking summary for detail + checkout. */
export function GroupBookingSummaryCard({
  package: pkg,
  seatCount = 1,
  totalFormatted,
  className,
}: GroupBookingSummaryCardProps) {
  const perSeat = `${pkg.currency} ${pkg.price_formatted}`;
  const total =
    totalFormatted != null
      ? `${pkg.currency} ${totalFormatted}`
      : `${pkg.currency} ${pkg.price_formatted}`;

  return (
    <aside
      className={className}
      data-testid="group-booking-summary"
      aria-label="Booking summary"
    >
      <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
        <p className="text-jp-xs font-semibold uppercase tracking-[0.14em] text-jp-muted">Booking summary</p>
        <div className="mt-3 flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-jp-md border border-jp-border bg-white">
            {pkg.airline_logo_url ? (
              <Image src={pkg.airline_logo_url} alt="" width={48} height={48} className="h-10 w-10 object-contain" />
            ) : (
              <span className="text-jp-xs font-bold text-jp-muted">
                {pkg.airline_code ?? pkg.airline_name.slice(0, 2).toUpperCase()}
              </span>
            )}
          </div>
          <div className="min-w-0">
            <p className="truncate text-jp-sm font-semibold text-jp-text">{pkg.airline_name}</p>
            <p className="text-jp-xs text-jp-muted">Group Ticketing</p>
          </div>
        </div>

        <dl className="mt-4 space-y-2 text-jp-sm">
          <div className="flex justify-between gap-3">
            <dt className="text-jp-muted">Route</dt>
            <dd className="text-right font-medium text-jp-text">{pkg.route_line}</dd>
          </div>
          {pkg.trip_type_label ? (
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Itinerary</dt>
              <dd className="font-medium text-jp-text">{pkg.trip_type_label}</dd>
            </div>
          ) : null}
          <div className="flex justify-between gap-3">
            <dt className="text-jp-muted">Departure</dt>
            <dd className="text-right font-medium text-jp-text">
              {pkg.departure_datetime_display ?? pkg.departure_date_short ?? "—"}
            </dd>
          </div>
          {pkg.arrival_time_display ? (
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Arrival</dt>
              <dd className="font-medium text-jp-text">{pkg.arrival_time_display}</dd>
            </div>
          ) : null}
          {pkg.category_name ? (
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Category</dt>
              <dd className="font-medium text-jp-text">{pkg.category_name}</dd>
            </div>
          ) : null}
          {pkg.baggage_line ? (
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Baggage</dt>
              <dd className="text-right font-medium text-jp-text">{pkg.baggage?.display ?? pkg.baggage_line}</dd>
            </div>
          ) : null}
          {pkg.meal_label ? (
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Meal</dt>
              <dd className="text-right font-medium text-jp-text">{pkg.meal_label}</dd>
            </div>
          ) : null}
          <div className="flex justify-between gap-3">
            <dt className="text-jp-muted">Seats</dt>
            <dd className="font-medium text-jp-text">
              {seatCount} selected · {pkg.seat_label}
            </dd>
          </div>
          <div className="flex justify-between gap-3 border-t border-jp-border pt-2">
            <dt className="text-jp-muted">Per-seat fare</dt>
            <dd className="font-semibold text-jp-text">{perSeat}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="font-semibold text-jp-text">Total</dt>
            <dd className="text-lg font-semibold tracking-[-0.02em] text-jp-text">{total}</dd>
          </div>
        </dl>

        <p className="mt-3 text-jp-xs text-jp-muted">
          Manual payment only. No payment at passenger entry. Supplier booking stays gated until authorized.
        </p>
      </div>
    </aside>
  );
}
