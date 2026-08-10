const DISPLAY_TIME_ZONE = "Asia/Karachi";

export const MONEY_UNAVAILABLE_LABEL = "Amount unavailable";
export const CURRENCY_NOT_RECORDED_LABEL = "Currency not recorded";

function parseIsoDate(iso: string): Date {
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    return new Date(`${iso}T12:00:00.000Z`);
  }
  return new Date(iso);
}

export function formatCurrency(amount: number, currency: string): string {
  const normalized = currency?.trim().toUpperCase();
  if (!normalized || !/^[A-Z]{3}$/.test(normalized)) {
    return MONEY_UNAVAILABLE_LABEL;
  }

  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: normalized,
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount);
}

export function formatDate(iso: string): string {
  const d = parseIsoDate(iso);
  if (Number.isNaN(d.getTime())) {
    return iso;
  }
  return d.toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
    timeZone: DISPLAY_TIME_ZONE,
  });
}

export function formatDateTime(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) {
    return iso;
  }
  return d.toLocaleString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    timeZone: DISPLAY_TIME_ZONE,
  });
}

export function tripTypeLabel(trip: "one_way" | "return"): string {
  return trip === "one_way" ? "One way" : "Return";
}
