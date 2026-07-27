import { AUDIT_REFERENCE_DATE } from "@/mocks/audit-fixtures";
import type { AuditDatePreset, AuditDateRange } from "@/types/audit";

const REF = new Date(AUDIT_REFERENCE_DATE);

function toIsoDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

function startOfMonth(d: Date): Date {
  return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1));
}

function endOfMonth(d: Date): Date {
  return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0));
}

function addDays(d: Date, days: number): Date {
  const next = new Date(d);
  next.setUTCDate(next.getUTCDate() + days);
  return next;
}

function parseIsoDate(value: string): Date | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
  const d = new Date(`${value}T00:00:00.000Z`);
  return Number.isNaN(d.getTime()) ? null : d;
}

export const AUDIT_DATE_PRESET_LABELS: Record<AuditDatePreset, string> = {
  last_24_hours: "Last 24 hours",
  last_7_days: "Last 7 days",
  last_30_days: "Last 30 days",
  this_month: "This month",
  previous_month: "Previous month",
  custom: "Custom",
};

export function resolveAuditDatePreset(
  preset: AuditDatePreset,
  customStart?: string,
  customEnd?: string,
): AuditDateRange {
  switch (preset) {
    case "last_24_hours":
      return {
        preset,
        startDate: toIsoDate(addDays(REF, -1)),
        endDate: toIsoDate(REF),
        valid: true,
        error: null,
      };
    case "last_7_days":
      return {
        preset,
        startDate: toIsoDate(addDays(REF, -6)),
        endDate: toIsoDate(REF),
        valid: true,
        error: null,
      };
    case "last_30_days":
      return {
        preset,
        startDate: toIsoDate(addDays(REF, -29)),
        endDate: toIsoDate(REF),
        valid: true,
        error: null,
      };
    case "this_month":
      return {
        preset,
        startDate: toIsoDate(startOfMonth(REF)),
        endDate: toIsoDate(REF),
        valid: true,
        error: null,
      };
    case "previous_month": {
      const prev = addDays(startOfMonth(REF), -1);
      return {
        preset,
        startDate: toIsoDate(startOfMonth(prev)),
        endDate: toIsoDate(endOfMonth(prev)),
        valid: true,
        error: null,
      };
    }
    case "custom":
      return validateCustomAuditDateRange(preset, customStart ?? "", customEnd ?? "");
    default:
      return resolveAuditDatePreset("last_30_days");
  }
}

export function validateCustomAuditDateRange(
  preset: AuditDatePreset,
  startDate: string,
  endDate: string,
): AuditDateRange {
  const start = parseIsoDate(startDate);
  const end = parseIsoDate(endDate);

  if (!start || !end) {
    return {
      preset,
      startDate: startDate || "",
      endDate: endDate || "",
      valid: false,
      error: "Enter valid dates in YYYY-MM-DD format.",
    };
  }

  if (start.getTime() > end.getTime()) {
    return {
      preset,
      startDate,
      endDate,
      valid: false,
      error: "Start date cannot be after end date.",
    };
  }

  return { preset, startDate, endDate, valid: true, error: null };
}

export function eventOccursInRange(occurredAt: string, range: AuditDateRange): boolean {
  if (!range.valid) return false;
  const day = occurredAt.slice(0, 10);
  return day >= range.startDate && day <= range.endDate;
}
