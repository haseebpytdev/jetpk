"use client";

import { useMemo, useState } from "react";
import { validateGeneralSettings } from "@/lib/access-control/settings-validation";
import { SettingsLocalPreviewForm, type SettingsPreviewField } from "@/features/settings/components/settings-local-preview-form";
import { SettingsValidationSummary } from "@/features/settings/components/settings-validation-summary";
import type { GeneralSettingsValues, SettingsModuleResult } from "@/types/settings-module";

const GENERAL_FIELDS: SettingsPreviewField[] = [
  { key: "organizationDisplayName", label: "Organization display name", type: "text", maxLength: 120 },
  { key: "publicSupportLabel", label: "Public support label", type: "text", maxLength: 120 },
  { key: "supportPhone", label: "Support phone", type: "tel" },
  { key: "supportEmail", label: "Support email", type: "email" },
  {
    key: "timezone",
    label: "Timezone",
    type: "select",
    options: [
      { value: "Asia/Karachi", label: "Asia/Karachi" },
      { value: "UTC", label: "UTC" },
      { value: "Asia/Dubai", label: "Asia/Dubai" },
      { value: "Europe/London", label: "Europe/London" },
    ],
  },
  {
    key: "defaultCurrency",
    label: "Default currency",
    type: "select",
    options: [
      { value: "PKR", label: "PKR" },
      { value: "USD", label: "USD" },
      { value: "AED", label: "AED" },
      { value: "GBP", label: "GBP" },
    ],
  },
  {
    key: "locale",
    label: "Locale",
    type: "select",
    options: [
      { value: "en-PK", label: "en-PK" },
      { value: "en-US", label: "en-US" },
      { value: "en-GB", label: "en-GB" },
    ],
  },
  {
    key: "dateFormat",
    label: "Date format",
    type: "select",
    options: [
      { value: "DD MMM YYYY", label: "DD MMM YYYY" },
      { value: "YYYY-MM-DD", label: "YYYY-MM-DD" },
      { value: "MM/DD/YYYY", label: "MM/DD/YYYY" },
    ],
  },
  { key: "operationalReferenceLabel", label: "Operational reference label", type: "text" },
  { key: "dashboardPaginationDefault", label: "Dashboard pagination default", type: "number", min: 5, max: 100 },
  { key: "reportingReferenceMetadata", label: "Reporting reference metadata", type: "textarea" },
];

type Props = {
  result: SettingsModuleResult;
};

export function GeneralSettingsWorkspace({ result }: Props) {
  const [previewValues, setPreviewValues] = useState<GeneralSettingsValues | null>(null);
  const baseline = result.general;
  const active = previewValues ?? baseline;
  const issues = useMemo(() => validateGeneralSettings(active), [active]);
  const dirty = previewValues !== null;

  return (
    <div className="space-y-4" data-testid="general-settings-workspace">
      <SettingsValidationSummary issues={issues} filter={result.query.validationState} />

      <SettingsLocalPreviewForm
        fields={GENERAL_FIELDS}
        baselineValues={baseline as unknown as Record<string, unknown>}
        onApply={(values) => setPreviewValues(values as unknown as GeneralSettingsValues)}
        onReset={() => setPreviewValues(null)}
        dirty={dirty}
      />

      <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="general-preview-values-heading">
        <h3 id="general-preview-values-heading" className="text-sm font-semibold text-gray-900">
          Active preview values
        </h3>
        <dl className="mt-3 grid gap-3 sm:grid-cols-2">
          {GENERAL_FIELDS.map((field) => (
            <div key={field.key} className="rounded-lg border border-jp-border px-3 py-2">
              <dt className="text-xs text-jp-muted">{field.label}</dt>
              <dd className="mt-1 text-sm font-medium break-all">{String(active[field.key as keyof GeneralSettingsValues] ?? "")}</dd>
            </div>
          ))}
        </dl>
      </section>
    </div>
  );
}
