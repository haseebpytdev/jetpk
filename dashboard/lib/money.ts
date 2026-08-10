import { formatCurrency, MONEY_UNAVAILABLE_LABEL, CURRENCY_NOT_RECORDED_LABEL } from "@/lib/format";

export type MoneyCurrencyStatus = "resolved" | "unresolved";

export type MoneyValue = {
  amount: string;
  amountMinor?: number;
  currency: string | null;
  currencyStatus: MoneyCurrencyStatus;
  currencySource?: string | null;
  displayLabel?: string;
  currencyLabel?: string | null;
  needsReview?: boolean;
};

export function isResolvedMoney(
  currency?: string | null,
  currencyStatus?: MoneyCurrencyStatus | string | null,
): boolean {
  if (currencyStatus === "unresolved") {
    return false;
  }

  const normalized = currency?.trim().toUpperCase() ?? "";
  return normalized.length === 3 && /^[A-Z]{3}$/.test(normalized);
}

export function formatMoneyDisplay(
  amountOrMoney: number | MoneyValue,
  currency?: string | null,
  currencyStatus?: MoneyCurrencyStatus | string | null,
): string {
  if (typeof amountOrMoney === "object" && amountOrMoney !== null) {
    if (amountOrMoney.displayLabel && !isResolvedMoney(amountOrMoney.currency, amountOrMoney.currencyStatus)) {
      return amountOrMoney.displayLabel;
    }

    if (!isResolvedMoney(amountOrMoney.currency, amountOrMoney.currencyStatus)) {
      return amountOrMoney.displayLabel ?? MONEY_UNAVAILABLE_LABEL;
    }

    const minor = amountOrMoney.amountMinor ?? Number.parseFloat(amountOrMoney.amount);
    return formatCurrency(minor, amountOrMoney.currency ?? "");
  }

  if (!isResolvedMoney(currency, currencyStatus)) {
    return MONEY_UNAVAILABLE_LABEL;
  }

  return formatCurrency(amountOrMoney, currency ?? "");
}

export function formatMoneyDetail(
  amountOrMoney: number | MoneyValue,
  currency?: string | null,
  currencyStatus?: MoneyCurrencyStatus | string | null,
): { primary: string; secondary?: string } {
  if (typeof amountOrMoney === "object" && amountOrMoney !== null) {
    if (!isResolvedMoney(amountOrMoney.currency, amountOrMoney.currencyStatus)) {
      return {
        primary: amountOrMoney.displayLabel ?? MONEY_UNAVAILABLE_LABEL,
        secondary: amountOrMoney.currencyLabel ?? CURRENCY_NOT_RECORDED_LABEL,
      };
    }

    const minor = amountOrMoney.amountMinor ?? Number.parseFloat(amountOrMoney.amount);
    const iso = (amountOrMoney.currency ?? "").trim().toUpperCase();
    return {
      primary: formatCurrency(minor, iso),
      secondary: iso,
    };
  }

  if (!isResolvedMoney(currency, currencyStatus)) {
    return {
      primary: MONEY_UNAVAILABLE_LABEL,
      secondary: CURRENCY_NOT_RECORDED_LABEL,
    };
  }

  const iso = currency?.trim().toUpperCase() ?? "";
  return {
    primary: formatCurrency(amountOrMoney, iso),
    secondary: iso,
  };
}
