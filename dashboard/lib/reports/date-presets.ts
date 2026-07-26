import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import type { ReportComparisonMode, ReportDatePreset, ReportDateRange } from "@/types/report";

const REF = new Date(REPORT_REFERENCE_DATE);

function toIsoDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

function startOfMonth(d: Date): Date {
  return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1));
}

function endOfMonth(d: Date): Date {
  return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0));
}

function startOfQuarter(d: Date): Date {
  const q = Math.floor(d.getUTCMonth() / 3);
  return new Date(Date.UTC(d.getUTCFullYear(), q * 3, 1));
}

function endOfQuarter(d: Date): Date {
  const start = startOfQuarter(d);
  return new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + 3, 0));
}

function startOfYear(d: Date): Date {
  return new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
}

function addDays(d: Date, days: number): Date {
  const next = new Date(d);
  next.setUTCDate(next.getUTCDate() + days);
  return next;
}

export function resolveDatePreset(preset: ReportDatePreset, customStart?: string, customEnd?: string): ReportDateRange {
  switch (preset) {
    case "last_7_days":
      return {
        preset,
        startDate: toIsoDate(addDays(REF, -6)),
        endDate: toIsoDate(REF),
      };
    case "last_30_days":
      return {
        preset,
        startDate: toIsoDate(addDays(REF, -29)),
        endDate: toIsoDate(REF),
      };
    case "current_month":
      return {
        preset,
        startDate: toIsoDate(startOfMonth(REF)),
        endDate: toIsoDate(REF),
      };
    case "previous_month": {
      const prev = new Date(Date.UTC(REF.getUTCFullYear(), REF.getUTCMonth() - 1, 15));
      return {
        preset,
        startDate: toIsoDate(startOfMonth(prev)),
        endDate: toIsoDate(endOfMonth(prev)),
      };
    }
    case "current_quarter":
      return {
        preset,
        startDate: toIsoDate(startOfQuarter(REF)),
        endDate: toIsoDate(REF),
      };
    case "previous_quarter": {
      const prevQ = new Date(Date.UTC(REF.getUTCFullYear(), REF.getUTCMonth() - 3, 15));
      return {
        preset,
        startDate: toIsoDate(startOfQuarter(prevQ)),
        endDate: toIsoDate(endOfQuarter(prevQ)),
      };
    }
    case "current_year":
      return {
        preset,
        startDate: toIsoDate(startOfYear(REF)),
        endDate: toIsoDate(REF),
      };
    case "custom":
      return {
        preset,
        startDate: customStart && /^\d{4}-\d{2}-\d{2}$/.test(customStart) ? customStart : toIsoDate(addDays(REF, -29)),
        endDate: customEnd && /^\d{4}-\d{2}-\d{2}$/.test(customEnd) ? customEnd : toIsoDate(REF),
      };
    default:
      return resolveDatePreset("last_30_days");
  }
}

export function resolveComparisonPeriod(
  mode: ReportComparisonMode,
  range: ReportDateRange,
): { startDate: string | null; endDate: string | null; label: string } {
  if (mode === "none") {
    return { startDate: null, endDate: null, label: "No comparison" };
  }

  const start = new Date(`${range.startDate}T12:00:00Z`);
  const end = new Date(`${range.endDate}T12:00:00Z`);
  const spanDays = Math.round((end.getTime() - start.getTime()) / 86_400_000) + 1;

  if (mode === "previous_period") {
    const compEnd = addDays(start, -1);
    const compStart = addDays(compEnd, -(spanDays - 1));
    return {
      startDate: toIsoDate(compStart),
      endDate: toIsoDate(compEnd),
      label: "Previous period",
    };
  }

  const compStart = new Date(Date.UTC(start.getUTCFullYear() - 1, start.getUTCMonth(), start.getUTCDate()));
  const compEnd = new Date(Date.UTC(end.getUTCFullYear() - 1, end.getUTCMonth(), end.getUTCDate()));
  return {
    startDate: toIsoDate(compStart),
    endDate: toIsoDate(compEnd),
    label: "Previous year",
  };
}

export const REPORT_DATE_PRESET_LABELS: Record<ReportDatePreset, string> = {
  last_7_days: "Last 7 days",
  last_30_days: "Last 30 days",
  current_month: "This month",
  previous_month: "Last month",
  current_quarter: "This quarter",
  previous_quarter: "Last quarter",
  current_year: "This year",
  custom: "Custom range",
};

export function validateCustomDateRange(
  preset: ReportDatePreset,
  startDate: string,
  endDate: string,
): { valid: boolean; message: string | null } {
  if (preset !== "custom") {
    return { valid: true, message: null };
  }
  const datePattern = /^\d{4}-\d{2}-\d{2}$/;
  if (!datePattern.test(startDate) || !datePattern.test(endDate)) {
    return { valid: false, message: "Enter valid dates in YYYY-MM-DD format." };
  }
  const start = new Date(`${startDate}T12:00:00Z`);
  const end = new Date(`${endDate}T12:00:00Z`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return { valid: false, message: "One or both dates are invalid." };
  }
  if (startDate > endDate) {
    return { valid: false, message: "Start date cannot be after end date." };
  }
  return { valid: true, message: null };
}
