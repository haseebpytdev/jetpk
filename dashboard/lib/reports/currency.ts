import type { ReportSupportedCurrency } from "@/lib/reports/constants";
import { REPORT_SUPPORTED_CURRENCIES } from "@/lib/reports/constants";

export type CurrencyTaggedAmount = {
  amount: number;
  currency: string;
};

export function isSupportedReportCurrency(currency: string): currency is ReportSupportedCurrency {
  return (REPORT_SUPPORTED_CURRENCIES as readonly string[]).includes(currency);
}

/** Sum amounts only when all rows share the same currency; otherwise returns null. */
export function sumSameCurrencyAmounts(rows: CurrencyTaggedAmount[]): {
  total: number | null;
  currency: ReportSupportedCurrency | null;
  mixed: boolean;
} {
  if (rows.length === 0) {
    return { total: 0, currency: "PKR", mixed: false };
  }

  const currencies = new Set(rows.map((r) => r.currency));
  if (currencies.size !== 1) {
    return { total: null, currency: null, mixed: true };
  }

  const currency = rows[0].currency;
  if (!isSupportedReportCurrency(currency)) {
    return { total: null, currency: null, mixed: true };
  }

  return {
    total: rows.reduce((sum, row) => sum + row.amount, 0),
    currency,
    mixed: false,
  };
}

export function filterByCurrency<T extends { currency: string }>(
  rows: T[],
  selected: ReportSupportedCurrency | "all",
): T[] {
  if (selected === "all") {
    return rows;
  }
  return rows.filter((row) => row.currency === selected);
}
