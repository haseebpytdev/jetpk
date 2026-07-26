import type { ReportGranularity, ReportSeries, ReportSeriesPoint } from "@/types/report";
import type { ReportSupportedCurrency } from "@/lib/reports/constants";

function toIsoDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

function addDays(d: Date, days: number): Date {
  const next = new Date(d);
  next.setUTCDate(next.getUTCDate() + days);
  return next;
}

function periodLabel(start: string, granularity: ReportGranularity): string {
  const d = new Date(`${start}T12:00:00Z`);
  if (granularity === "day") {
    return d.toLocaleDateString("en-GB", { day: "numeric", month: "short" });
  }
  if (granularity === "week") {
    return `W/C ${d.toLocaleDateString("en-GB", { day: "numeric", month: "short" })}`;
  }
  if (granularity === "quarter") {
    const q = Math.floor(d.getUTCMonth() / 3) + 1;
    return `Q${q} ${d.getUTCFullYear()}`;
  }
  return d.toLocaleDateString("en-GB", { month: "short", year: "numeric" });
}

function bucketKey(isoDate: string, granularity: ReportGranularity): string {
  const d = new Date(`${isoDate}T12:00:00Z`);
  if (granularity === "day") return toIsoDate(d);
  if (granularity === "week") {
    const day = d.getUTCDay();
    const diff = day === 0 ? -6 : 1 - day;
    return toIsoDate(addDays(d, diff));
  }
  if (granularity === "quarter") {
    const q = Math.floor(d.getUTCMonth() / 3);
    return toIsoDate(new Date(Date.UTC(d.getUTCFullYear(), q * 3, 1)));
  }
  return toIsoDate(new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1)));
}

function generateBuckets(startDate: string, endDate: string, granularity: ReportGranularity): string[] {
  const buckets: string[] = [];
  let cursor = new Date(`${startDate}T12:00:00Z`);
  const end = new Date(`${endDate}T12:00:00Z`);
  while (cursor <= end) {
    const key = bucketKey(toIsoDate(cursor), granularity);
    if (!buckets.includes(key)) buckets.push(key);
    if (granularity === "day") cursor = addDays(cursor, 1);
    else if (granularity === "week") cursor = addDays(cursor, 7);
    else if (granularity === "quarter") cursor = new Date(Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth() + 3, 1));
    else cursor = new Date(Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth() + 1, 1));
  }
  return buckets;
}

export function buildTimeSeries(
  key: string,
  label: string,
  startDate: string,
  endDate: string,
  granularity: ReportGranularity,
  rows: { date: string; value: number }[],
  currency: ReportSupportedCurrency | null,
): ReportSeries {
  const buckets = generateBuckets(startDate, endDate, granularity);
  const totals = new Map<string, number>();
  for (const row of rows) {
    const bucket = bucketKey(row.date, granularity);
    totals.set(bucket, (totals.get(bucket) ?? 0) + row.value);
  }
  const points: ReportSeriesPoint[] = buckets.map((bucket) => ({
    periodStart: bucket,
    periodEnd: bucket,
    label: periodLabel(bucket, granularity),
    value: totals.get(bucket) ?? 0,
  }));
  return { key, label, currency, points };
}
