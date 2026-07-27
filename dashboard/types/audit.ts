import type {
  AuditActorType,
  AuditAuthorizationOutcome,
  AuditCategory,
  AuditChannel,
  AuditOutcome,
  AuditRiskState,
  AuditSeverity,
  AuditTargetType,
  ValidationState,
} from "@/types/access-control";

export type AuditDatePreset =
  | "last_24_hours"
  | "last_7_days"
  | "last_30_days"
  | "this_month"
  | "previous_month"
  | "custom";

export type AuditSortField =
  | "occurredAt"
  | "id"
  | "category"
  | "type"
  | "severity"
  | "outcome"
  | "sourceModule"
  | "riskState"
  | "validationState";

export type SortDirection = "asc" | "desc";

export type AuditQuery = {
  search: string;
  category: AuditCategory | "all";
  eventType: string;
  severity: AuditSeverity | "all";
  outcome: AuditOutcome | "all";
  actorType: AuditActorType | "all";
  actor: string;
  targetType: AuditTargetType | "all";
  sourceModule: string;
  risk: AuditRiskState | "all";
  authorization: AuditAuthorizationOutcome | "all";
  channel: AuditChannel | "all";
  datePreset: AuditDatePreset;
  startDate: string;
  endDate: string;
  validationState: ValidationState | "all";
  securityView: boolean;
  page: number;
  pageSize: number;
  sort: AuditSortField;
  direction: SortDirection;
  selected: string | null;
  state: string;
  previewError: boolean;
  previewLoading: boolean;
  previewEmpty: boolean;
};

export type AuditSummaryMetrics = {
  totalEvents: number;
  securityEvents: number;
  warningCriticalEvents: number;
  successfulOutcomes: number;
  deniedOutcomes: number;
  previewOnlyEvents: number;
  highRiskEvents: number;
  eventsRequiringReview: number;
};

export type AuditTableRow = {
  id: string;
  occurredAt: string;
  category: AuditCategory;
  type: string;
  eventLabel: string;
  actorName: string;
  actorType: AuditActorType;
  targetLabel: string;
  targetType: AuditTargetType;
  sourceModule: string;
  severity: AuditSeverity;
  outcome: AuditOutcome;
  riskState: AuditRiskState;
  channel: AuditChannel;
  validationState: ValidationState;
  previewOnly: boolean;
};

export type AuditDateRange = {
  preset: AuditDatePreset;
  startDate: string;
  endDate: string;
  valid: boolean;
  error: string | null;
};

export type AuditExportManifest = {
  title: string;
  columns: string[];
  rowCount: number;
  filename: string;
  previewOnly: boolean;
};

export type AuditModuleResult = {
  state: "ready" | "loading" | "empty" | "error";
  query: AuditQuery;
  dateRange: AuditDateRange;
  summary: AuditSummaryMetrics;
  table: {
    rows: AuditTableRow[];
    total: number;
    page: number;
    pageSize: number;
    pageCount: number;
  };
  facets: {
    categories: AuditCategory[];
    eventTypes: string[];
    severities: AuditSeverity[];
    outcomes: AuditOutcome[];
    actorTypes: AuditActorType[];
    actors: { id: string; name: string }[];
    targetTypes: AuditTargetType[];
    sourceModules: string[];
    riskStates: AuditRiskState[];
    authorizationOutcomes: AuditAuthorizationOutcome[];
    channels: AuditChannel[];
    validationStates: ValidationState[];
  };
  selectedEvent: import("@/types/access-control").AuditEvent | null;
  exportEvents: import("@/types/access-control").AuditEvent[];
  exportManifest: AuditExportManifest;
  securityEventCount: number;
};
