import { buildAuditExportManifest } from "@/lib/audit/export-preview";
import type { AuditEvent, AuditActorType, AuditCategory, AuditChannel, AuditOutcome, AuditRiskState, AuditSeverity, AuditTargetType, ValidationState } from "@/types/access-control";
import type { AuditModuleResult, AuditQuery, AuditTableRow } from "@/types/audit";
import type { LaravelAuditListPayload } from "@/lib/read-only/laravel/types";

export function transformAuditModule(
  payload: LaravelAuditListPayload,
  query: AuditQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedEvent: AuditEvent | null,
): AuditModuleResult {
  const rows = payload.events as AuditTableRow[];

  return {
    state: pagination.total === 0 ? "empty" : "ready",
    query,
    dateRange: { preset: query.datePreset, startDate: query.startDate, endDate: query.endDate, valid: true, error: null },
    summary: payload.summary ?? {
      totalEvents: pagination.total,
      securityEvents: rows.filter((e) => e.category === "security").length,
      warningCriticalEvents: rows.filter((e) => e.severity === "warning" || e.severity === "critical").length,
      successfulOutcomes: rows.filter((e) => e.outcome === "success").length,
      deniedOutcomes: rows.filter((e) => e.outcome === "failure").length,
      previewOnlyEvents: rows.filter((e) => e.previewOnly).length,
      highRiskEvents: rows.filter((e) => e.riskState === "elevated" || e.riskState === "high").length,
      eventsRequiringReview: 0,
    },
    table: {
      rows,
      total: pagination.total,
      page: pagination.page,
      pageSize: pagination.pageSize,
      pageCount: pagination.pageCount,
    },
    facets: {
      categories: [...new Set(rows.map((e) => e.category))] as AuditCategory[],
      eventTypes: [...new Set(rows.map((e) => e.type))],
      severities: [...new Set(rows.map((e) => e.severity))] as AuditSeverity[],
      outcomes: [...new Set(rows.map((e) => e.outcome))] as AuditOutcome[],
      actorTypes: [...new Set(rows.map((e) => e.actorType))] as AuditActorType[],
      actors: [...new Map(rows.map((e) => [e.actorName, { id: e.actorName, name: e.actorName }])).values()],
      targetTypes: [...new Set(rows.map((e) => e.targetType))] as AuditTargetType[],
      sourceModules: [...new Set(rows.map((e) => e.sourceModule))],
      riskStates: [...new Set(rows.map((e) => e.riskState))] as AuditRiskState[],
      authorizationOutcomes: ["allowed"],
      channels: [...new Set(rows.map((e) => e.channel))] as AuditChannel[],
      validationStates: [...new Set(rows.map((e) => e.validationState))] as ValidationState[],
    },
    selectedEvent,
    exportEvents: selectedEvent ? [selectedEvent] : [],
    exportManifest: buildAuditExportManifest(selectedEvent ? [selectedEvent] : []),
    securityEventCount: rows.filter((e) => e.category === "security").length,
  };
}

export function transformAuditDetail(payload: Record<string, unknown>): AuditEvent {
  const actor = (payload.actor as Record<string, unknown> | undefined) ?? {};
  const target = (payload.target as Record<string, unknown> | undefined) ?? {};
  const metadata = (payload.metadata as Record<string, unknown> | undefined) ?? {};

  return {
    id: String(payload.id ?? ""),
    type: (payload.type as AuditEvent["type"]) ?? "user.recordViewed",
    category: (payload.category as AuditCategory) ?? "security",
    severity: (payload.severity as AuditSeverity) ?? "info",
    outcome: (payload.outcome as AuditOutcome) ?? "success",
    actor: {
      actorType: (actor.actorType as AuditActorType) ?? "system",
      userId: actor.userId ? String(actor.userId) : null,
      displayName: String(actor.displayName ?? payload.actorName ?? "System"),
      roleLabel: actor.roleLabel ? String(actor.roleLabel) : null,
      department: actor.department ? String(actor.department) : null,
      status: (actor.status as AuditEvent["actor"]["status"]) ?? null,
      highRiskAccess: Boolean(actor.highRiskAccess),
    },
    target: {
      type: (target.type as AuditTargetType) ?? "dashboard",
      id: String(target.id ?? "—"),
      label: String(target.label ?? payload.targetLabel ?? "—"),
    },
    actionLabel: String(payload.eventLabel ?? payload.type ?? "Audit event"),
    summary: String(payload.changeSummary ?? payload.eventLabel ?? "Audit event"),
    occurredAt: String(payload.occurredAt ?? ""),
    sourceModule: String(payload.sourceModule ?? "security"),
    permissionKey: payload.permissionKey ? String(payload.permissionKey) : null,
    authorizationOutcome: (payload.authorization as AuditEvent["authorizationOutcome"]) ?? "allowed",
    roleContext: null,
    riskState: (payload.risk as AuditRiskState) ?? "standard",
    changeSummary: payload.changeSummary ? String(payload.changeSummary) : null,
    validationState: (payload.validationState as ValidationState) ?? "valid",
    metadata: {
      maskedIp: metadata.maskedIp ? String(metadata.maskedIp) : null,
      maskedNetworkRange: metadata.maskedNetworkRange ? String(metadata.maskedNetworkRange) : String(payload.maskedNetworkRange ?? null),
      userAgentSummary: metadata.userAgentSummary ? String(metadata.userAgentSummary) : String(payload.userAgentSummary ?? null),
      channel: (metadata.channel as AuditChannel) ?? (payload.channel as AuditChannel) ?? "dashboard",
      scope: metadata.scope ? String(metadata.scope) : null,
      module: String(metadata.module ?? payload.sourceModule ?? "security"),
      route: null,
      previewOnly: Boolean(payload.previewOnly ?? true),
      correlationId: null,
      referenceLabel: null,
      retentionCategory: "operational",
      syntheticSource: "fixture",
    },
  };
}
