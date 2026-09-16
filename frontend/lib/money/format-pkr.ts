/**
 * Shared whole-PKR customer money formatter.
 * Contract: `PKR 88,114` (en-US grouping). No Rs., no trailing currency, no Approx.
 */

export function formatWholePkr(amount: number | null | undefined): string | null {
  if (amount === null || amount === undefined || !Number.isFinite(amount) || amount < 0) {
    return null;
  }
  return `PKR ${Math.round(amount).toLocaleString("en-US")}`;
}

export function formatDisplayPrice(amount: number | null | undefined): string {
  return formatWholePkr(amount) ?? "Fare unavailable";
}

export function priceAccessibleLabel(amount: number | null | undefined): string {
  const visible = formatDisplayPrice(amount);
  if (visible === "Fare unavailable") return "Fare unavailable";
  return `Select fare for ${visible}`;
}

/** Strip legacy Approx. / Rs. prefixes from server strings for display parity. */
export function normalizeCustomerPriceLabel(raw: string | null | undefined): string | null {
  if (!raw) return null;
  let value = raw.trim();
  if (!value) return null;
  value = value.replace(/^approx\.?\s*/i, "").trim();
  value = value.replace(/^rs\.?\s*/i, "PKR ").trim();
  if (/^\d/.test(value) && !/^pkr\b/i.test(value)) {
    const n = Number(value.replace(/,/g, ""));
    if (Number.isFinite(n)) return formatWholePkr(n);
  }
  if (/^pkr\s+/i.test(value)) {
    const numeric = value.replace(/^pkr\s+/i, "").replace(/,/g, "");
    const n = Number(numeric);
    if (Number.isFinite(n)) return formatWholePkr(n);
    return `PKR ${value.replace(/^pkr\s+/i, "").trim()}`;
  }
  return value;
}
