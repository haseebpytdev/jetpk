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
    <header className="space-y-2">
      <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Group package</p>
      <h1 className="text-2xl font-semibold text-jp-text">{pkg.title}</h1>
      <p className="text-jp-sm text-jp-muted">{pkg.route_line}</p>
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
};

export function GroupPriceBlock({ currency, priceFormatted, totalFormatted, seatCount }: GroupPriceBlockProps) {
  return (
    <div className="rounded-jp-md border border-jp-border bg-jp-surface p-4">
      <p className="text-jp-xs uppercase tracking-wide text-jp-muted">Price per seat</p>
      <p className="text-xl font-semibold text-jp-text">{currency} {priceFormatted}</p>
      {totalFormatted && seatCount ? (
        <p className="mt-1 text-jp-sm text-jp-muted">Total for {seatCount} seat{seatCount === 1 ? "" : "s"}: {currency} {totalFormatted}</p>
      ) : null}
    </div>
  );
}
