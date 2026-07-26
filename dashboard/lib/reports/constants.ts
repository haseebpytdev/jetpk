/** Deterministic reference date for DASH-08-09 fixtures and report presets. */
export const REPORT_REFERENCE_DATE = "2026-07-01T00:00:00.000Z";

/** ISO date portion aligned with operational fixture range (bookings from 2026-01-05). */
export const REPORT_FIXTURE_EPOCH = "2026-01-01";

export const REPORT_SUPPORTED_CURRENCIES = ["PKR"] as const;

export type ReportSupportedCurrency = (typeof REPORT_SUPPORTED_CURRENCIES)[number];

export const CMS_BRAND_ID = "jetpakistan" as const;

export const CMS_BRAND_LABEL = "JetPakistan" as const;
