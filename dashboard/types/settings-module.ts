import type { AccessValidationIssue, SettingsSection } from "@/types/access-control";

export type SettingsQuery = {
  selectedSection: SettingsSection | "overview";
  validationState: "all" | "valid" | "warning" | "blocked";
  state: string;
  preview: boolean;
  tab: string;
  previewError: boolean;
  previewLoading: boolean;
  previewEmpty: boolean;
};

export type SettingsOverviewMetrics = {
  generalState: "ready" | "warning" | "incomplete";
  securityPolicyState: "ready" | "warning" | "incomplete";
  notificationState: "ready" | "warning" | "incomplete";
  integrationState: "ready" | "warning" | "incomplete";
  settingsRequiringReview: number;
  highRiskPolicyWarnings: number;
  incompleteMetadata: number;
  lastFixtureRevision: string;
};

export type GeneralSettingsValues = {
  organizationDisplayName: string;
  publicSupportLabel: string;
  supportPhone: string;
  supportEmail: string;
  timezone: string;
  defaultCurrency: string;
  locale: string;
  dateFormat: string;
  operationalReferenceLabel: string;
  dashboardPaginationDefault: number;
  reportingReferenceMetadata: string;
};

export type SecuritySettingsValues = {
  mfaRequirementPolicy: string;
  privilegedRoleMfaPolicy: string;
  passwordMinLength: number;
  passwordComplexityPolicy: string;
  sessionDurationHours: number;
  idleTimeoutMinutes: number;
  failedLoginThreshold: number;
  lockoutDurationMinutes: number;
  invitationExpiryDays: number;
  highRiskApprovalPolicy: string;
  auditRetentionDays: number;
  sessionConcurrencyPolicy: string;
};

export type NotificationCategoryConfig = {
  key: string;
  label: string;
  enabled: boolean;
  emailChannel: boolean;
  dashboardChannel: boolean;
  severityThreshold: string;
  recipientRoles: string[];
  deliveryMode: "immediate" | "digest";
};

export type NotificationSettingsValues = {
  categories: NotificationCategoryConfig[];
};

export type IntegrationRecord = {
  key: string;
  displayName: string;
  channel: string;
  enabled: boolean;
  readinessStatus: string;
  environmentLabel: string;
  lastSyntheticCheck: string;
  configurationCompleteness: number;
  capabilitySummary: string;
  warningState: boolean;
  futureOwner: string;
  documentationReference: string;
};

export type IntegrationSettingsValues = {
  integrations: IntegrationRecord[];
};

export type SettingsModuleResult = {
  state: "ready" | "loading" | "empty" | "error";
  query: SettingsQuery;
  overview: SettingsOverviewMetrics;
  general: GeneralSettingsValues;
  security: SecuritySettingsValues;
  notifications: NotificationSettingsValues;
  integrations: IntegrationSettingsValues;
  validationIssues: AccessValidationIssue[];
  categoryReadiness: { section: SettingsSection; label: string; ready: boolean; issueCount: number }[];
};
