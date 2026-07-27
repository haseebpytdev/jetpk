import type { SettingsCategoryDefinition, SettingsSection } from "@/types/access-control";

export const SETTINGS_CATEGORIES: SettingsCategoryDefinition[] = [
  {
    section: "general",
    label: "General",
    description: "Organization display and operational reference labels.",
    fields: [
      { key: "org.displayName", section: "general", label: "Organization display name", description: "Public-facing organization name.", type: "text", value: "JetPakistan", readOnly: true, sensitive: false },
      { key: "org.supportEmail", section: "general", label: "Support email", description: "Primary support contact email.", type: "email", value: "support@jetpakistan.example", readOnly: true, sensitive: false },
      { key: "org.supportPhone", section: "general", label: "Support phone", description: "Primary support phone number.", type: "phone", value: "+92 21 111 000 000", readOnly: true, sensitive: false },
      { key: "org.timezone", section: "general", label: "Timezone", description: "Default operational timezone.", type: "select", value: "Asia/Karachi", readOnly: true, sensitive: false },
      { key: "org.defaultCurrency", section: "general", label: "Default currency", description: "Default currency for reports.", type: "select", value: "PKR", readOnly: true, sensitive: false },
      { key: "org.dateFormat", section: "general", label: "Date format", description: "Display date format.", type: "select", value: "DD MMM YYYY", readOnly: true, sensitive: false },
      { key: "org.locale", section: "general", label: "Locale", description: "Default locale.", type: "select", value: "en-PK", readOnly: true, sensitive: false },
    ],
  },
  {
    section: "security",
    label: "Security",
    description: "MFA, password policy, and session metadata (no secrets).",
    fields: [
      { key: "security.mfaPolicy", section: "security", label: "MFA policy", description: "Multi-factor authentication requirement level.", type: "select", value: "required_for_admin", readOnly: true, sensitive: false },
      { key: "security.passwordMinLength", section: "security", label: "Password minimum length", description: "Minimum password length metadata.", type: "number", value: 12, readOnly: true, sensitive: false },
      { key: "security.sessionDurationHours", section: "security", label: "Session duration (hours)", description: "Maximum session duration metadata.", type: "duration", value: 8, readOnly: true, sensitive: false },
      { key: "security.failedLoginThreshold", section: "security", label: "Failed login threshold", description: "Lockout after failed attempts.", type: "number", value: 5, readOnly: true, sensitive: false },
      { key: "security.lockoutDurationMinutes", section: "security", label: "Lockout duration (minutes)", description: "Account lockout duration metadata.", type: "duration", value: 30, readOnly: true, sensitive: false },
      { key: "security.highRiskApprovalPolicy", section: "security", label: "High-risk approval policy", description: "Whether high-risk actions require approval.", type: "select", value: "enabled", readOnly: true, sensitive: false },
    ],
  },
  {
    section: "notifications",
    label: "Notifications",
    description: "Alert channel enablement metadata.",
    fields: [
      { key: "notifications.bookingAlerts", section: "notifications", label: "Booking alerts", description: "Booking alert notifications.", type: "boolean", value: true, readOnly: true, sensitive: false },
      { key: "notifications.paymentAlerts", section: "notifications", label: "Payment alerts", description: "Payment alert notifications.", type: "boolean", value: true, readOnly: true, sensitive: false },
      { key: "notifications.pnrAlerts", section: "notifications", label: "PNR/order alerts", description: "PNR and order alert notifications.", type: "boolean", value: true, readOnly: true, sensitive: false },
      { key: "notifications.ticketingAlerts", section: "notifications", label: "Ticketing alerts", description: "Ticketing alert notifications.", type: "boolean", value: true, readOnly: true, sensitive: false },
      { key: "notifications.cmsReviewAlerts", section: "notifications", label: "CMS review alerts", description: "CMS review notifications.", type: "boolean", value: true, readOnly: true, sensitive: false },
      { key: "notifications.securityAlerts", section: "notifications", label: "Security alerts", description: "Security event notifications.", type: "boolean", value: true, readOnly: true, sensitive: false },
      { key: "notifications.auditAlerts", section: "notifications", label: "Audit alerts", description: "Audit event notifications.", type: "boolean", value: true, readOnly: true, sensitive: false },
    ],
  },
  {
    section: "integrations",
    label: "Integrations",
    description: "Supplier and channel status metadata only — no credentials.",
    fields: [
      { key: "integrations.sabreGds", section: "integrations", label: "Sabre GDS", description: "Sabre GDS connection status.", type: "metadata", value: "configured_preview", readOnly: true, sensitive: false },
      { key: "integrations.sabreNdc", section: "integrations", label: "Sabre NDC", description: "Sabre NDC connection status.", type: "metadata", value: "configured_preview", readOnly: true, sensitive: false },
      { key: "integrations.oneApi", section: "integrations", label: "One API", description: "One API channel status.", type: "metadata", value: "enabled_preview", readOnly: true, sensitive: false },
      { key: "integrations.manual", section: "integrations", label: "Manual channel", description: "Manual booking channel status.", type: "metadata", value: "enabled", readOnly: true, sensitive: false },
      { key: "integrations.mock", section: "integrations", label: "Mock supplier", description: "Mock supplier for preview.", type: "metadata", value: "enabled_preview", readOnly: true, sensitive: false },
      { key: "integrations.webhookReadiness", section: "integrations", label: "Webhook readiness", description: "Webhook endpoint readiness metadata.", type: "metadata", value: "not_configured", readOnly: true, sensitive: false },
      { key: "integrations.emailDelivery", section: "integrations", label: "Email delivery", description: "Email delivery status metadata.", type: "metadata", value: "preview_only", readOnly: true, sensitive: false },
    ],
  },
];

export function getSettingsCategory(section: SettingsSection): SettingsCategoryDefinition | undefined {
  return SETTINGS_CATEGORIES.find((c) => c.section === section);
}
