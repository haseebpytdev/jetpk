/** Authoritative price display — matches Blade `formatPkrAmount`. */

export function formatDisplayPrice(amount: number | null | undefined): string {
  if (amount === null || amount === undefined || !Number.isFinite(amount) || amount <= 0) {
    return "Fare unavailable";
  }
  return `PKR ${Math.round(amount).toLocaleString("en-US")}`;
}

export function formatWholePkr(amount: number | null | undefined): string | null {
  if (amount === null || amount === undefined || !Number.isFinite(amount) || amount <= 0) return null;
  return `PKR ${Math.round(amount).toLocaleString("en-US")}`;
}

export function priceAccessibleLabel(amount: number | null | undefined): string {
  const visible = formatDisplayPrice(amount);
  if (visible === "Fare unavailable") return "Fare unavailable";
  return `Select fare for ${visible}`;
}
