import { buildCsvContent } from "@/lib/csv-safe";
import type { AuditEvent } from "@/types/access-control";
import type { AuditExportManifest } from "@/types/audit";

export const AUDIT_EXPORT_COLUMNS = [
  "eventId",
  "timestamp",
  "category",
  "eventType",
  "actorDisplayName",
  "actorType",
  "targetLabel",
  "targetType",
  "sourceModule",
  "severity",
  "outcome",
  "risk",
  "channel",
  "maskedNetworkRange",
  "validationState",
] as const;

const COLUMN_HEADERS: Record<(typeof AUDIT_EXPORT_COLUMNS)[number], string> = {
  eventId: "Event ID",
  timestamp: "Timestamp",
  category: "Category",
  eventType: "Event type",
  actorDisplayName: "Actor",
  actorType: "Actor type",
  targetLabel: "Target",
  targetType: "Target type",
  sourceModule: "Source module",
  severity: "Severity",
  outcome: "Outcome",
  risk: "Risk",
  channel: "Channel",
  maskedNetworkRange: "Masked network",
  validationState: "Validation state",
};

export function buildAuditExportManifest(events: AuditEvent[]): AuditExportManifest {
  const start = events.length > 0 ? events[0]!.occurredAt.slice(0, 10) : "2026-06-01";
  const end = events.length > 0 ? events[events.length - 1]!.occurredAt.slice(0, 10) : "2026-07-01";
  return {
    title: "JetPakistan audit export preview",
    columns: AUDIT_EXPORT_COLUMNS.map((c) => COLUMN_HEADERS[c]),
    rowCount: events.length,
    filename: `jetpakistan-audit-preview-${start}-to-${end}.csv`,
    previewOnly: true,
  };
}

function eventToExportRow(event: AuditEvent): (string | null)[] {
  return [
    event.id,
    event.occurredAt,
    event.category,
    event.type,
    event.actor.displayName,
    event.actor.actorType,
    event.target.label,
    event.target.type,
    event.sourceModule,
    event.severity,
    event.outcome,
    event.riskState,
    event.metadata.channel ?? "",
    event.metadata.maskedNetworkRange ?? event.metadata.maskedIp ?? "",
    event.validationState,
  ];
}

export function buildAuditExportCsv(events: AuditEvent[]): string {
  const sorted = [...events].sort((a, b) => {
    const cmp = a.occurredAt.localeCompare(b.occurredAt);
    return cmp !== 0 ? cmp : a.id.localeCompare(b.id);
  });
  const headers = AUDIT_EXPORT_COLUMNS.map((c) => COLUMN_HEADERS[c]);
  const rows = sorted.map((e) => eventToExportRow(e));
  return buildCsvContent(headers, rows);
}

export function downloadAuditExportCsv(events: AuditEvent[]): void {
  const manifest = buildAuditExportManifest(events);
  const content = buildAuditExportCsv(events);
  const blob = new Blob([content], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = manifest.filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

export function exportManifestExcludesSensitiveFields(manifest: AuditExportManifest): boolean {
  const forbidden = [
    "password",
    "token",
    "session",
    "cookie",
    "authorization",
    "apiKey",
    "secret",
    "ipAddress",
    "userAgent",
    "header",
  ];
  const joined = manifest.columns.join(" ").toLowerCase();
  return !forbidden.some((f) => joined.includes(f));
}
