"use client";

import { formatMoneyDetail } from "@/lib/money";
import type { MoneyCurrencyStatus } from "@/lib/money";

type Props = {
  amount: number;
  currency?: string | null;
  currencyStatus?: MoneyCurrencyStatus | string | null;
  className?: string;
  valueClassName?: string;
  secondaryClassName?: string;
};

/** Operational money display — never shows bare amounts without ISO currency. */
export function MoneyDisplay({
  amount,
  currency,
  currencyStatus,
  className,
  valueClassName = "tabular-nums",
  secondaryClassName = "text-xs text-amber-800",
}: Props) {
  const detail = formatMoneyDetail(amount, currency, currencyStatus);

  return (
    <span className={className}>
      <span className={valueClassName} data-testid="money-display-primary">{detail.primary}</span>
      {detail.secondary ? (
        <span className={`mt-0.5 block ${secondaryClassName}`} data-testid="money-display-secondary">
          {detail.secondary}
        </span>
      ) : null}
    </span>
  );
}
