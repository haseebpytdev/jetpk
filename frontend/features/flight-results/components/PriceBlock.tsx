import { cn } from "@/lib/cn";
import { formatDisplayPrice, priceAccessibleLabel } from "../utils/price";

type PriceBlockProps = {
  amount?: number | null;
  priceDisplay?: string;
  disabled?: boolean;
  loading?: boolean;
  onSelect?: () => void;
  className?: string;
  testId?: string;
};

export function PriceBlock({
  amount,
  priceDisplay,
  disabled,
  loading,
  onSelect,
  className,
  testId,
}: PriceBlockProps) {
  const visible = priceDisplay && priceDisplay !== "Fare unavailable" ? priceDisplay : formatDisplayPrice(amount);
  const unavailable = visible === "Fare unavailable" || disabled;

  return (
    <button
      type="button"
      data-testid={testId ?? "result-price-button"}
      className={cn(
        "inline-flex min-h-[2.75rem] min-w-[7.5rem] items-center justify-center rounded-jp-md px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary focus-visible:ring-offset-2",
        unavailable
          ? "cursor-not-allowed border border-jp-border bg-jp-surface-muted text-jp-text-muted"
          : "bg-jp-primary text-white hover:bg-jp-primary-hover active:bg-jp-primary-active",
        loading && "opacity-70",
        className,
      )}
      disabled={unavailable || loading}
      aria-busy={loading}
      aria-label={unavailable ? "Fare unavailable" : priceAccessibleLabel(amount)}
      onClick={onSelect}
    >
      <span className="whitespace-nowrap">{loading ? "…" : visible}</span>
    </button>
  );
}
