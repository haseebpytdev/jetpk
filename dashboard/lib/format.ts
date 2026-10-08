const MONTH_SHORT = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"] as const;

/** Pakistan Standard Time offset — deterministic across Node SSR and browsers (no ICU drift). */
const PKT_OFFSET_MS = 5 * 60 * 60 * 1000;

function parseIsoDate(iso: string): Date {
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    return new Date(`${iso}T12:00:00.000Z`);
  }
  return new Date(iso);
}

function formatPktCalendarParts(epochMs: number): { day: number; month: number; year: number } {
  const pkt = new Date(epochMs + PKT_OFFSET_MS);
  return {
    day: pkt.getUTCDate(),
    month: pkt.getUTCMonth(),
    year: pkt.getUTCFullYear(),
  };
}

function formatGroupedInteger(amount: number): string {
  const rounded = Math.round(amount);
  const negative = rounded < 0;
  const digits = Math.abs(rounded).toString();
  const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  return negative ? `-${grouped}` : grouped;
}

export function formatCurrency(amount: number, currency: string): string {
  const grouped = formatGroupedInteger(amount);
  if (currency === "PKR") {
    return `Rs ${grouped}`;
  }
  return `${currency} ${grouped}`;
}

export function formatDate(iso: string): string {
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    const [year, month, day] = iso.split("-").map((part) => Number.parseInt(part, 10));
    return `${day} ${MONTH_SHORT[month - 1]} ${year}`;
  }
  const d = parseIsoDate(iso);
  if (Number.isNaN(d.getTime())) {
    return iso;
  }
  const { day, month, year } = formatPktCalendarParts(d.getTime());
  return `${day} ${MONTH_SHORT[month]} ${year}`;
}

export function formatDateTime(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) {
    return iso;
  }
  const { day, month, year } = formatPktCalendarParts(d.getTime());
  const pkt = new Date(d.getTime() + PKT_OFFSET_MS);
  const hours = pkt.getUTCHours();
  const minutes = pkt.getUTCMinutes();
  const hh = hours < 10 ? `0${hours}` : String(hours);
  const mm = minutes < 10 ? `0${minutes}` : String(minutes);
  return `${day} ${MONTH_SHORT[month]} ${year}, ${hh}:${mm}`;
}

export function tripTypeLabel(trip: "one_way" | "return" | "round_trip" | string): string {
  if (trip === "one_way") {
    return "One way";
  }
  if (trip === "return" || trip === "round_trip" || trip === "roundtrip") {
    return "Return";
  }
  return String(trip || "—");
}
