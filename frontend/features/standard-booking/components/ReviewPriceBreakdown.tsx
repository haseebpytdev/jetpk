import type { AuthoritativePricing } from "../types/review-payment";

type ReviewPriceBreakdownProps = {
  pricing: AuthoritativePricing;
};

export function ReviewPriceBreakdown({ pricing }: ReviewPriceBreakdownProps) {
  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="review-price-breakdown">
      <h2 className="text-jp-base font-semibold">Price breakdown</h2>
      <dl className="mt-3 space-y-2 text-jp-sm">
        <div className="flex justify-between gap-4"><dt>Base fare</dt><dd>{pricing.currency} {pricing.base_fare.toLocaleString()}</dd></div>
        <div className="flex justify-between gap-4"><dt>Taxes</dt><dd>{pricing.currency} {pricing.taxes.toLocaleString()}</dd></div>
        <div className="flex justify-between gap-4"><dt>Service charges</dt><dd>{pricing.currency} {pricing.service_charges.toLocaleString()}</dd></div>
        <div className="flex justify-between gap-4 border-t border-jp-border pt-2 font-semibold">
          <dt>Total</dt>
          <dd data-testid="authoritative-total">{pricing.formatted_total}</dd>
        </div>
      </dl>
    </article>
  );
}
