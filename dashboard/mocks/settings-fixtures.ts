export const SETTINGS_FIXTURE_REVISION = "2026-07-01T00:00:00.000Z";

import type {
  GeneralSettingsValues,
  IntegrationSettingsValues,
  NotificationSettingsValues,
  SecuritySettingsValues,
} from "@/types/settings-module";

export const FIXTURE_GENERAL_SETTINGS: GeneralSettingsValues = {
  organizationDisplayName: "JetPakistan",
  publicSupportLabel: "JetPakistan Support",
  supportPhone: "+92 21 111 000 000",
  supportEmail: "support@jetpakistan.example",
  timezone: "Asia/Karachi",
  defaultCurrency: "PKR",
  locale: "en-PK",
  dateFormat: "DD MMM YYYY",
  operationalReferenceLabel: "JP-OPS",
  dashboardPaginationDefault: 20,
  reportingReferenceMetadata: "JetPakistan OTA reporting baseline",
};

export const FIXTURE_SECURITY_SETTINGS: SecuritySettingsValues = {
  mfaRequirementPolicy: "required_for_admin",
  privilegedRoleMfaPolicy: "required",
  passwordMinLength: 12,
  passwordComplexityPolicy: "upper_lower_digit_special",
  sessionDurationHours: 8,
  idleTimeoutMinutes: 30,
  failedLoginThreshold: 5,
  lockoutDurationMinutes: 30,
  invitationExpiryDays: 14,
  highRiskApprovalPolicy: "enabled",
  auditRetentionDays: 365,
  sessionConcurrencyPolicy: "single_primary",
};

export const FIXTURE_NOTIFICATION_SETTINGS: NotificationSettingsValues = {
  categories: [
    { key: "booking", label: "Booking", enabled: true, emailChannel: true, dashboardChannel: true, severityThreshold: "notice", recipientRoles: ["operations_manager", "booking_agent"], deliveryMode: "immediate" },
    { key: "payment", label: "Payment", enabled: true, emailChannel: true, dashboardChannel: true, severityThreshold: "warning", recipientRoles: ["finance_officer"], deliveryMode: "immediate" },
    { key: "pnr", label: "PNR/order", enabled: true, emailChannel: false, dashboardChannel: true, severityThreshold: "notice", recipientRoles: ["operations_manager", "pnr_reviewer"], deliveryMode: "immediate" },
    { key: "ticketing", label: "Ticketing", enabled: true, emailChannel: true, dashboardChannel: true, severityThreshold: "warning", recipientRoles: ["ticketing_agent"], deliveryMode: "immediate" },
    { key: "supplier", label: "Supplier", enabled: true, emailChannel: false, dashboardChannel: true, severityThreshold: "notice", recipientRoles: ["operations_manager"], deliveryMode: "digest" },
    { key: "cmsReview", label: "CMS review", enabled: true, emailChannel: true, dashboardChannel: true, severityThreshold: "notice", recipientRoles: ["content_manager"], deliveryMode: "immediate" },
    { key: "userSecurity", label: "User security", enabled: true, emailChannel: true, dashboardChannel: true, severityThreshold: "critical", recipientRoles: ["administrator", "super_administrator"], deliveryMode: "immediate" },
    { key: "audit", label: "Audit", enabled: true, emailChannel: false, dashboardChannel: true, severityThreshold: "warning", recipientRoles: ["read_only_auditor"], deliveryMode: "digest" },
    { key: "systemHealth", label: "System health", enabled: true, emailChannel: true, dashboardChannel: true, severityThreshold: "critical", recipientRoles: ["administrator"], deliveryMode: "immediate" },
  ],
};

export const FIXTURE_INTEGRATION_SETTINGS: IntegrationSettingsValues = {
  integrations: [
    {
      key: "sabreGds",
      displayName: "Sabre GDS",
      channel: "Sabre GDS",
      enabled: true,
      readinessStatus: "Configured (preview)",
      environmentLabel: "preview",
      lastSyntheticCheck: "2026-06-30T12:00:00.000Z",
      configurationCompleteness: 85,
      capabilitySummary: "GDS search, PNR retrieval, ticketing metadata",
      warningState: false,
      futureOwner: "Supplier integration team",
      documentationReference: "docs/suppliers/sabre-gds.md",
    },
    {
      key: "sabreNdc",
      displayName: "Sabre NDC",
      channel: "Sabre NDC",
      enabled: true,
      readinessStatus: "Configured (preview)",
      environmentLabel: "preview",
      lastSyntheticCheck: "2026-06-30T12:00:00.000Z",
      configurationCompleteness: 80,
      capabilitySummary: "NDC order management, offer pricing metadata",
      warningState: false,
      futureOwner: "Supplier integration team",
      documentationReference: "docs/suppliers/sabre-ndc.md",
    },
    {
      key: "oneApi",
      displayName: "One API",
      channel: "One API",
      enabled: true,
      readinessStatus: "Enabled (preview)",
      environmentLabel: "preview",
      lastSyntheticCheck: "2026-06-29T08:00:00.000Z",
      configurationCompleteness: 75,
      capabilitySummary: "One API channel search and booking metadata",
      warningState: false,
      futureOwner: "Supplier integration team",
      documentationReference: "docs/suppliers/one-api.md",
    },
    {
      key: "emailDelivery",
      displayName: "Email delivery",
      channel: "Email",
      enabled: true,
      readinessStatus: "Preview only",
      environmentLabel: "preview",
      lastSyntheticCheck: "2026-06-28T10:00:00.000Z",
      configurationCompleteness: 60,
      capabilitySummary: "Transactional email metadata — no SMTP credentials in preview",
      warningState: true,
      futureOwner: "Platform operations",
      documentationReference: "docs/integrations/email.md",
    },
    {
      key: "paymentProvider",
      displayName: "Payment provider",
      channel: "Payments",
      enabled: false,
      readinessStatus: "Placeholder",
      environmentLabel: "not_configured",
      lastSyntheticCheck: "2026-06-01T00:00:00.000Z",
      configurationCompleteness: 20,
      capabilitySummary: "Payment gateway placeholder metadata",
      warningState: true,
      futureOwner: "Finance platform",
      documentationReference: "docs/integrations/payments.md",
    },
    {
      key: "cmsDelivery",
      displayName: "CMS delivery/API",
      channel: "CMS",
      enabled: true,
      readinessStatus: "Preview ready",
      environmentLabel: "preview",
      lastSyntheticCheck: "2026-06-30T14:00:00.000Z",
      configurationCompleteness: 90,
      capabilitySummary: "CMS content delivery and API readiness metadata",
      warningState: false,
      futureOwner: "Content platform",
      documentationReference: "docs/cms/api-readiness.md",
    },
    {
      key: "auditPersistence",
      displayName: "Audit persistence",
      channel: "Audit",
      enabled: true,
      readinessStatus: "Fixture only",
      environmentLabel: "preview",
      lastSyntheticCheck: "2026-06-30T00:00:00.000Z",
      configurationCompleteness: 50,
      capabilitySummary: "Audit event persistence readiness — fixture-backed",
      warningState: false,
      futureOwner: "Security platform",
      documentationReference: "docs/audit/persistence.md",
    },
    {
      key: "webhookReadiness",
      displayName: "Webhook readiness",
      channel: "Webhooks",
      enabled: false,
      readinessStatus: "Not configured",
      environmentLabel: "not_configured",
      lastSyntheticCheck: "2026-06-01T00:00:00.000Z",
      configurationCompleteness: 10,
      capabilitySummary: "Webhook endpoint readiness metadata — no secrets",
      warningState: true,
      futureOwner: "Platform operations",
      documentationReference: "docs/integrations/webhooks.md",
    },
  ],
};

export function cloneGeneralSettings(): GeneralSettingsValues {
  return { ...FIXTURE_GENERAL_SETTINGS };
}

export function cloneSecuritySettings(): SecuritySettingsValues {
  return { ...FIXTURE_SECURITY_SETTINGS };
}

export function cloneNotificationSettings(): NotificationSettingsValues {
  return {
    categories: FIXTURE_NOTIFICATION_SETTINGS.categories.map((c) => ({ ...c, recipientRoles: [...c.recipientRoles] })),
  };
}

export function cloneIntegrationSettings(): IntegrationSettingsValues {
  return {
    integrations: FIXTURE_INTEGRATION_SETTINGS.integrations.map((i) => ({ ...i })),
  };
}
