import { eventOccursInRange, resolveAuditDatePreset } from "@/lib/audit/date-presets";
import { isSecurityAuditEvent } from "@/lib/audit/audit-validation";
import type { AuditEvent } from "@/types/access-control";
import type {
  AuditQuery,
  AuditSortField,
  AuditSummaryMetrics,
  AuditTableRow,
} from "@/types/audit";

export function countActiveAuditFilters(query: AuditQuery): number {
  let count = 0;
  if (query.search) count += 1;
  if (query.category !== "all") count += 1;
  if (query.eventType) count += 1;
  if (query.severity !== "all") count += 1;
  if (query.outcome !== "all") count += 1;
  if (query.actorType !== "all") count += 1;
  if (query.actor) count += 1;
  if (query.targetType !== "all") count += 1;
  if (query.sourceModule) count += 1;
  if (query.risk !== "all") count += 1;
  if (query.authorization !== "all") count += 1;
  if (query.channel !== "all") count += 1;
  if (query.datePreset !== "last_30_days") count += 1;
  if (query.validationState !== "all") count += 1;
  if (query.securityView) count += 1;
  return count;
}

function matchesSearch(event: AuditEvent, search: string): boolean {
  if (!search) return true;
  const q = search.toLowerCase();
  return (
    event.id.toLowerCase().includes(q) ||
    event.summary.toLowerCase().includes(q) ||
    event.actionLabel.toLowerCase().includes(q) ||
    event.actor.displayName.toLowerCase().includes(q) ||
    event.target.label.toLowerCase().includes(q) ||
    event.type.toLowerCase().includes(q)
  );
}

function filterEvents(events: AuditEvent[], query: AuditQuery, dateRange: ReturnType<typeof resolveAuditDatePreset>): AuditEvent[] {
  const filtered = events.filter((event) => {
    if (!matchesSearch(event, query.search)) return false;
    if (query.category !== "all" && event.category !== query.category) return false;
    if (query.eventType && event.type !== query.eventType) return false;
    if (query.severity !== "all" && event.severity !== query.severity) return false;
    if (query.outcome !== "all" && event.outcome !== query.outcome) return false;
    if (query.actorType !== "all" && event.actor.actorType !== query.actorType) return false;
    if (query.actor && event.actor.userId !== query.actor && event.actor.displayName !== query.actor) return false;
    if (query.targetType !== "all" && event.target.type !== query.targetType) return false;
    if (query.sourceModule && event.sourceModule !== query.sourceModule) return false;
    if (query.risk !== "all" && event.riskState !== query.risk) return false;
    if (query.authorization !== "all" && event.authorizationOutcome !== query.authorization) return false;
    if (query.channel !== "all" && event.metadata.channel !== query.channel) return false;
    if (query.validationState !== "all" && event.validationState !== query.validationState) return false;
    if (query.securityView && !isSecurityAuditEvent(event)) return false;
    if (dateRange.valid && !eventOccursInRange(event.occurredAt, dateRange)) return false;
    return true;
  });
  return filtered;
}

function severityPriority(severity: AuditEvent["severity"]): number {
  const order: Record<AuditEvent["severity"], number> = {
    critical: 0,
    warning: 1,
    notice: 2,
    info: 3,
  };
  return order[severity];
}

function sortEvents(events: AuditEvent[], sort: AuditSortField, direction: "asc" | "desc"): AuditEvent[] {
  const dir = direction === "asc" ? 1 : -1;
  return [...events].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "occurredAt":
        cmp = a.occurredAt.localeCompare(b.occurredAt);
        break;
      case "id":
        cmp = a.id.localeCompare(b.id);
        break;
      case "category":
        cmp = a.category.localeCompare(b.category);
        break;
      case "type":
        cmp = a.type.localeCompare(b.type);
        break;
      case "severity":
        cmp = severityPriority(a.severity) - severityPriority(b.severity);
        break;
      case "outcome":
        cmp = a.outcome.localeCompare(b.outcome);
        break;
      case "sourceModule":
        cmp = a.sourceModule.localeCompare(b.sourceModule);
        break;
      case "riskState":
        cmp = a.riskState.localeCompare(b.riskState);
        break;
      case "validationState":
        cmp = a.validationState.localeCompare(b.validationState);
        break;
      default:
        cmp = a.occurredAt.localeCompare(b.occurredAt);
    }
    if (cmp === 0) cmp = a.id.localeCompare(b.id);
    return cmp * dir;
  });
}

function toTableRow(event: AuditEvent): AuditTableRow {
  return {
    id: event.id,
    occurredAt: event.occurredAt,
    category: event.category,
    type: event.type,
    eventLabel: event.actionLabel,
    actorName: event.actor.displayName,
    actorType: event.actor.actorType,
    targetLabel: event.target.label,
    targetType: event.target.type,
    sourceModule: event.sourceModule,
    severity: event.severity,
    outcome: event.outcome,
    riskState: event.riskState,
    channel: event.metadata.channel,
    validationState: event.validationState,
    previewOnly: event.metadata.previewOnly,
  };
}

export function computeAuditSummary(events: AuditEvent[]): AuditSummaryMetrics {
  return {
    totalEvents: events.length,
    securityEvents: events.filter(isSecurityAuditEvent).length,
    warningCriticalEvents: events.filter((e) => e.severity === "warning" || e.severity === "critical").length,
    successfulOutcomes: events.filter((e) => e.outcome === "success").length,
    deniedOutcomes: events.filter((e) => e.authorizationOutcome === "denied" || e.outcome === "failure").length,
    previewOnlyEvents: events.filter((e) => e.metadata.previewOnly).length,
    highRiskEvents: events.filter((e) => e.riskState === "high" || e.riskState === "critical").length,
    eventsRequiringReview: events.filter((e) => e.validationState === "review" || e.validationState === "warning").length,
  };
}

export function buildAuditFacets(events: AuditEvent[]) {
  const uniq = <T,>(values: T[]) => [...new Set(values)];
  const actors = new Map<string, string>();
  for (const e of events) {
    if (e.actor.userId) actors.set(e.actor.userId, e.actor.displayName);
  }
  return {
    categories: uniq(events.map((e) => e.category)).sort(),
    eventTypes: uniq(events.map((e) => e.type)).sort(),
    severities: uniq(events.map((e) => e.severity)).sort(),
    outcomes: uniq(events.map((e) => e.outcome)).sort(),
    actorTypes: uniq(events.map((e) => e.actor.actorType)).sort(),
    actors: [...actors.entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name)),
    targetTypes: uniq(events.map((e) => e.target.type)).sort(),
    sourceModules: uniq(events.map((e) => e.sourceModule)).sort(),
    riskStates: uniq(events.map((e) => e.riskState)).sort(),
    authorizationOutcomes: uniq(events.map((e) => e.authorizationOutcome)).sort(),
    channels: uniq(events.map((e) => e.metadata.channel)).sort(),
    validationStates: uniq(events.map((e) => e.validationState)).sort(),
  };
}

export function buildAuditPage(query: AuditQuery, sourceEvents: AuditEvent[]) {
  const dateRange = resolveAuditDatePreset(query.datePreset, query.startDate, query.endDate);
  const filtered = filterEvents(sourceEvents, query, dateRange);
  const sorted = sortEvents(filtered, query.sort, query.direction);
  const pageCount = Math.max(1, Math.ceil(sorted.length / query.pageSize));
  const page = Math.min(query.page, pageCount);
  const start = (page - 1) * query.pageSize;
  const pageRows = sorted.slice(start, start + query.pageSize);

  return {
    dateRange,
    summary: dateRange.valid ? computeAuditSummary(filtered) : {
      totalEvents: 0,
      securityEvents: 0,
      warningCriticalEvents: 0,
      successfulOutcomes: 0,
      deniedOutcomes: 0,
      previewOnlyEvents: 0,
      highRiskEvents: 0,
      eventsRequiringReview: 0,
    },
    rows: pageRows.map(toTableRow),
    events: pageRows,
    allFilteredEvents: sorted,
    total: sorted.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    facets: buildAuditFacets(sourceEvents),
    securityEventCount: sourceEvents.filter(isSecurityAuditEvent).length,
  };
}
