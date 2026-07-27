"use client";

import { AUDIT_DATE_PRESET_LABELS } from "@/lib/audit/date-presets";
import type { AuditDateRange, AuditQuery } from "@/types/audit";

type Props = {
  query: AuditQuery;
  dateRange: AuditDateRange;
};

export function AuditActiveFilters({ query, dateRange }: Props) {
  const chips: { label: string; value: string }[] = [];

  if (query.search) chips.push({ label: "Search", value: query.search });
  if (query.category !== "all") chips.push({ label: "Category", value: query.category });
  if (query.eventType) chips.push({ label: "Event type", value: query.eventType });
  if (query.severity !== "all") chips.push({ label: "Severity", value: query.severity });
  if (query.outcome !== "all") chips.push({ label: "Outcome", value: query.outcome });
  if (query.actorType !== "all") chips.push({ label: "Actor type", value: query.actorType });
  if (query.actor) chips.push({ label: "Actor", value: query.actor });
  if (query.targetType !== "all") chips.push({ label: "Target type", value: query.targetType });
  if (query.sourceModule) chips.push({ label: "Module", value: query.sourceModule });
  if (query.risk !== "all") chips.push({ label: "Risk", value: query.risk });
  if (query.authorization !== "all") chips.push({ label: "Authorization", value: query.authorization });
  if (query.channel !== "all" && query.channel) chips.push({ label: "Channel", value: query.channel });
  if (query.validationState !== "all") chips.push({ label: "Validation", value: query.validationState });
  if (query.securityView) chips.push({ label: "View", value: "Security events" });
  if (query.datePreset !== "last_30_days") {
    chips.push({
      label: "Period",
      value: `${AUDIT_DATE_PRESET_LABELS[query.datePreset]} (${dateRange.startDate} — ${dateRange.endDate})`,
    });
  }

  if (chips.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2" data-testid="audit-active-filters" aria-label="Active audit filters">
      {chips.map((chip) => (
        <span key={`${chip.label}-${chip.value}`} className="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-800">
          <span className="font-medium">{chip.label}:</span> {chip.value}
        </span>
      ))}
    </div>
  );
}
