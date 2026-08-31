/**
 * Compact endpoint date for result-card hierarchy (DATE above TIME).
 * Accepts backend displays like "Fri, 17 Oct", "17 Oct", or ISO-ish strings.
 * Output: "17 OCT"
 */
export function compactEndpointDate(value?: string | null): string {
  const raw = typeof value === "string" ? value.trim() : "";
  if (!raw) return "";

  const monthMap: Record<string, string> = {
    jan: "JAN",
    january: "JAN",
    feb: "FEB",
    february: "FEB",
    mar: "MAR",
    march: "MAR",
    apr: "APR",
    april: "APR",
    may: "MAY",
    jun: "JUN",
    june: "JUN",
    jul: "JUL",
    july: "JUL",
    aug: "AUG",
    august: "AUG",
    sep: "SEP",
    sept: "SEP",
    september: "SEP",
    oct: "OCT",
    october: "OCT",
    nov: "NOV",
    november: "NOV",
    dec: "DEC",
    december: "DEC",
  };

  // "Fri, 17 Oct" / "17 Oct 2026" / "17 OCT"
  const human = raw.match(
    /(?:(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)\w*,?\s*)?(\d{1,2})\s+([A-Za-z]{3,9})(?:\s+\d{2,4})?/i,
  );
  if (human) {
    const day = human[1];
    const month = monthMap[human[2].toLowerCase()] ?? human[2].slice(0, 3).toUpperCase();
    return `${day} ${month}`;
  }

  const iso = Date.parse(raw);
  if (!Number.isNaN(iso)) {
    const d = new Date(iso);
    const day = String(d.getUTCDate());
    const months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
    return `${day} ${months[d.getUTCMonth()]}`;
  }

  return raw;
}
