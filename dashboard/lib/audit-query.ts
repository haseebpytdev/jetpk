import type {
  AuditActorType,
  AuditAuthorizationOutcome,
  AuditCategory,
  AuditOutcome,
  AuditRiskState,
  AuditSeverity,
  AuditTargetType,
  ValidationState,
} from "@/types/access-control";
import type { AuditDatePreset, AuditQuery, AuditSortField, SortDirection } from "@/types/audit";
import { resolveAuditDatePreset } from "@/lib/audit/date-presets";

const DEFAULT_PAGE_SIZE = 20;

const CATEGORIES: AuditCategory[] = [
  "authentication",
  "users",
  "roles",
  "permissions",
  "bookings",
  "operations",
  "reports",
  "cms",
  "settings",
  "security",
];

const SEVERITIES: AuditSeverity[] = ["info", "notice", "warning", "critical"];
const OUTCOMES: AuditOutcome[] = ["success", "failure", "partial", "preview"];
const ACTOR_TYPES: AuditActorType[] = [
  "dashboardUser",
  "system",
  "supplierChannel",
  "anonymousPreview",
  "scheduledProcess",
];
const TARGET_TYPES: AuditTargetType[] = [
  "user",
  "role",
  "permission",
  "booking",
  "payment",
  "customer",
  "supplier",
  "agent",
  "pnrOrder",
  "ticketDocument",
  "report",
  "cmsPage",
  "cmsSection",
  "setting",
  "integration",
  "auditEvent",
  "dashboard",
];
const RISK_STATES: AuditRiskState[] = ["none", "low", "elevated", "high", "critical"];
const AUTH_OUTCOMES: AuditAuthorizationOutcome[] = [
  "allowed",
  "denied",
  "requiresApproval",
  "unavailable",
  "notApplicable",
];
const VALIDATION_STATES: ValidationState[] = ["valid", "warning", "blocked", "review"];
const DATE_PRESETS: AuditDatePreset[] = [
  "last_24_hours",
  "last_7_days",
  "last_30_days",
  "this_month",
  "previous_month",
  "custom",
];
const SORT_FIELDS: AuditSortField[] = [
  "occurredAt",
  "id",
  "category",
  "type",
  "severity",
  "outcome",
  "sourceModule",
  "riskState",
  "validationState",
];

function first(value: string | string[] | undefined): string {
  if (Array.isArray(value)) return value[0] ?? "";
  return value ?? "";
}

function parseEnum<T extends string>(raw: string, allowed: readonly T[], fallback: T | "all"): T | "all" {
  if (!raw || raw === "all") return fallback;
  return (allowed as readonly string[]).includes(raw) ? (raw as T) : fallback;
}

function parsePositiveInt(raw: string, fallback: number, max?: number): number {
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n < 1) return fallback;
  if (max !== undefined && n > max) return max;
  return n;
}

function parsePageSize(raw: string): number {
  const n = parsePositiveInt(raw, DEFAULT_PAGE_SIZE);
  if (n === 10 || n === 20 || n === 50) return n;
  return DEFAULT_PAGE_SIZE;
}

export function parseAuditQuery(
  searchParams: Record<string, string | string[] | undefined>,
): AuditQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);
  const presetRaw = first(searchParams.datePreset);
  const preset = (DATE_PRESETS as readonly string[]).includes(presetRaw)
    ? (presetRaw as AuditDatePreset)
    : "last_30_days";
  const defaultRange = resolveAuditDatePreset(preset);

  return {
    search: first(searchParams.search).trim() || first(searchParams.q).trim(),
    category: parseEnum(first(searchParams.category), CATEGORIES, "all"),
    eventType: first(searchParams.eventType),
    severity: parseEnum(first(searchParams.severity), SEVERITIES, "all"),
    outcome: parseEnum(first(searchParams.outcome), OUTCOMES, "all"),
    actorType: parseEnum(first(searchParams.actorType), ACTOR_TYPES, "all"),
    actor: first(searchParams.actor),
    targetType: parseEnum(first(searchParams.targetType), TARGET_TYPES, "all"),
    sourceModule: first(searchParams.sourceModule),
    risk: parseEnum(first(searchParams.risk), RISK_STATES, "all"),
    authorization: parseEnum(first(searchParams.authorization), AUTH_OUTCOMES, "all"),
    channel: (() => {
      const raw = first(searchParams.channel);
      if (!raw || raw === "all") return "all" as const;
      const allowed = ["gds", "ndc", "oneApi", "manual", "mock", "dashboard"] as const;
      return (allowed as readonly string[]).includes(raw) ? (raw as (typeof allowed)[number]) : "all";
    })(),
    datePreset: preset,
    startDate: first(searchParams.startDate) || defaultRange.startDate,
    endDate: first(searchParams.endDate) || defaultRange.endDate,
    validationState: parseEnum(first(searchParams.validationState), VALIDATION_STATES, "all"),
    securityView: first(searchParams.securityView) === "1",
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as AuditSortField) ? (sortRaw as AuditSortField) : "occurredAt",
    direction: directionRaw === "asc" || directionRaw === "desc" ? (directionRaw as SortDirection) : "desc",
    selected: first(searchParams.selected) || null,
    state: first(searchParams.state),
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
    previewEmpty: first(searchParams.previewEmpty) === "1",
  };
}

export function auditQueryToSearchParams(query: AuditQuery, overrides?: Partial<AuditQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.search) params.set("search", merged.search);
  if (merged.category !== "all") params.set("category", merged.category);
  if (merged.eventType) params.set("eventType", merged.eventType);
  if (merged.severity !== "all") params.set("severity", merged.severity);
  if (merged.outcome !== "all") params.set("outcome", merged.outcome);
  if (merged.actorType !== "all") params.set("actorType", merged.actorType);
  if (merged.actor) params.set("actor", merged.actor);
  if (merged.targetType !== "all") params.set("targetType", merged.targetType);
  if (merged.sourceModule) params.set("sourceModule", merged.sourceModule);
  if (merged.risk !== "all") params.set("risk", merged.risk);
  if (merged.authorization !== "all") params.set("authorization", merged.authorization);
  if (merged.channel !== "all" && merged.channel) params.set("channel", merged.channel);
  if (merged.datePreset !== "last_30_days") params.set("datePreset", merged.datePreset);
  if (merged.datePreset === "custom") {
    if (merged.startDate) params.set("startDate", merged.startDate);
    if (merged.endDate) params.set("endDate", merged.endDate);
  }
  if (merged.validationState !== "all") params.set("validationState", merged.validationState);
  if (merged.securityView) params.set("securityView", "1");
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "occurredAt") params.set("sort", merged.sort);
  if (merged.direction !== "desc") params.set("direction", merged.direction);
  if (merged.selected) params.set("selected", merged.selected);
  if (merged.state) params.set("state", merged.state);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");
  if (merged.previewEmpty) params.set("previewEmpty", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultAuditQuery(): AuditQuery {
  return parseAuditQuery({});
}
