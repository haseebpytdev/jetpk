"use client";

import { useMemo, useState } from "react";
import { validateSecuritySettings } from "@/lib/access-control/settings-validation";
import { SettingsLocalPreviewForm, type SettingsPreviewField } from "@/features/settings/components/settings-local-preview-form";
import { SettingsValidationSummary } from "@/features/settings/components/settings-validation-summary";
import type { SecuritySettingsValues, SettingsModuleResult } from "@/types/settings-module";

const SECURITY_FIELDS: SettingsPreviewField[] = [
  {
    key: "mfaRequirementPolicy",
    label: "MFA requirement policy",
    type: "select",
    options: [
      { value: "required_for_admin", label: "Required for admin" },
      { value: "optional", label: "Optional" },
      { value: "disabled", label: "Disabled" },
    ],
  },
  {
    key: "privilegedRoleMfaPolicy",
    label: "Privileged role MFA policy",
    type: "select",
    options: [
      { value: "required", label: "Required" },
      { value: "optional", label: "Optional" },
      { value: "disabled", label: "Disabled" },
    ],
  },
  { key: "passwordMinLength", label: "Password minimum length", type: "number", min: 8, max: 128 },
  {
    key: "passwordComplexityPolicy",
    label: "Password complexity policy",
    type: "select",
    options: [
      { value: "upper_lower_digit_special", label: "Upper, lower, digit, special" },
      { value: "upper_lower_digit", label: "Upper, lower, digit" },
      { value: "basic", label: "Basic" },
    ],
  },
  { key: "sessionDurationHours", label: "Session duration (hours)", type: "number", min: 1, max: 48 },
  { key: "idleTimeoutMinutes", label: "Idle timeout (minutes)", type: "number", min: 5, max: 240 },
  { key: "failedLoginThreshold", label: "Failed login threshold", type: "number", min: 1, max: 20 },
  { key: "lockoutDurationMinutes", label: "Lockout duration (minutes)", type: "number", min: 1, max: 1440 },
  { key: "invitationExpiryDays", label: "Invitation expiry (days)", type: "number", min: 1, max: 90 },
  {
    key: "highRiskApprovalPolicy",
    label: "High-risk approval policy",
    type: "select",
    options: [
      { value: "enabled", label: "Enabled" },
      { value: "disabled", label: "Disabled" },
    ],
  },
  { key: "auditRetentionDays", label: "Audit retention (days)", type: "number", min: 30, max: 3650 },
  {
    key: "sessionConcurrencyPolicy",
    label: "Session concurrency policy",
    type: "select",
    options: [
      { value: "single_primary", label: "Single primary" },
      { value: "multiple", label: "Multiple" },
    ],
  },
];

type Props = {
  result: SettingsModuleResult;
};

export function SecuritySettingsWorkspace({ result }: Props) {
  const [previewValues, setPreviewValues] = useState<SecuritySettingsValues | null>(null);
  const baseline = result.security;
  const active = previewValues ?? baseline;
  const issues = useMemo(() => validateSecuritySettings(active), [active]);
  const dirty = previewValues !== null;

  return (
    <div className="space-y-4" data-testid="security-settings-workspace">
      <SettingsValidationSummary issues={issues} filter={result.query.validationState} />

      <SettingsLocalPreviewForm
        fields={SECURITY_FIELDS}
        baselineValues={baseline as unknown as Record<string, unknown>}
        onApply={(values) => setPreviewValues(values as unknown as SecuritySettingsValues)}
        onReset={() => setPreviewValues(null)}
        dirty={dirty}
      />

      <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="security-preview-values-heading">
        <h3 id="security-preview-values-heading" className="text-sm font-semibold text-gray-900">
          Active preview values
        </h3>
        <dl className="mt-3 grid gap-3 sm:grid-cols-2">
          {SECURITY_FIELDS.map((field) => (
            <div key={field.key} className="rounded-lg border border-jp-border px-3 py-2">
              <dt className="text-xs text-jp-muted">{field.label}</dt>
              <dd className="mt-1 text-sm font-medium break-all">{String(active[field.key as keyof SecuritySettingsValues] ?? "")}</dd>
            </div>
          ))}
        </dl>
      </section>
    </div>
  );
}
