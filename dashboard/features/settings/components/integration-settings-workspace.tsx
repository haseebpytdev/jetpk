"use client";

import { useMemo, useState } from "react";
import { ChannelBadge, CmsStatusBadge } from "@/components/ui/status-badge";
import { formatDateTime } from "@/lib/format";
import { validateIntegrationSettings } from "@/lib/access-control/settings-validation";
import { SettingsLocalPreviewForm, type SettingsPreviewField } from "@/features/settings/components/settings-local-preview-form";
import { SettingsValidationSummary } from "@/features/settings/components/settings-validation-summary";
import { useMockData } from "@/lib/preview";
import type { IntegrationRecord, IntegrationSettingsValues, SettingsModuleResult } from "@/types/settings-module";

const INTEGRATION_FIELDS: SettingsPreviewField[] = [
  {
    key: "selectedIntegration",
    label: "Integration",
    type: "select",
    options: [],
    description: "Select an integration record to preview metadata changes.",
  },
  { key: "enabled", label: "Integration enabled", type: "boolean" },
  {
    key: "environmentLabel",
    label: "Environment label",
    type: "select",
    options: [
      { value: "preview", label: "Preview" },
      { value: "staging", label: "Staging" },
      { value: "production", label: "Production" },
      { value: "not_configured", label: "Not configured" },
    ],
  },
  { key: "configurationCompleteness", label: "Configuration completeness (%)", type: "number", min: 0, max: 100 },
  { key: "capabilitySummary", label: "Capability summary", type: "textarea" },
];

function integrationToFormValues(integration: IntegrationRecord): Record<string, unknown> {
  return {
    selectedIntegration: integration.key,
    enabled: integration.enabled,
    environmentLabel: integration.environmentLabel,
    configurationCompleteness: integration.configurationCompleteness,
    capabilitySummary: integration.capabilitySummary,
  };
}

function applyIntegrationPreview(
  baseline: IntegrationSettingsValues,
  preview: IntegrationSettingsValues | null,
  formValues: Record<string, unknown>,
): IntegrationSettingsValues {
  const source = preview ?? baseline;
  const key = String(formValues.selectedIntegration ?? "");
  return {
    integrations: source.integrations.map((integration) =>
      integration.key === key
        ? {
            ...integration,
            enabled: Boolean(formValues.enabled),
            environmentLabel: String(formValues.environmentLabel ?? integration.environmentLabel),
            configurationCompleteness: Number(formValues.configurationCompleteness ?? integration.configurationCompleteness),
            capabilitySummary: String(formValues.capabilitySummary ?? integration.capabilitySummary),
          }
        : integration,
    ),
  };
}

type Props = {
  result: SettingsModuleResult;
};

export function IntegrationSettingsWorkspace({ result }: Props) {
  const allowLocalPreview = useMockData();
  const baseline = result.integrations;
  const [previewValues, setPreviewValues] = useState<IntegrationSettingsValues | null>(null);
  const active = allowLocalPreview ? (previewValues ?? baseline) : baseline;
  const issues = useMemo(() => validateIntegrationSettings(active), [active]);
  const dirty = allowLocalPreview && previewValues !== null;
  const initialIntegration = baseline.integrations[0];
  const fields = useMemo(
    () =>
      INTEGRATION_FIELDS.map((field) =>
        field.key === "selectedIntegration"
          ? {
              ...field,
              options: baseline.integrations.map((integration) => ({
                value: integration.key,
                label: integration.displayName,
              })),
            }
          : field,
      ),
    [baseline.integrations],
  );
  const formBaseline = initialIntegration ? integrationToFormValues(initialIntegration) : {};

  return (
    <div className="space-y-4" data-testid="integration-settings-workspace">
      <SettingsValidationSummary issues={issues} filter={result.query.validationState} />

      {allowLocalPreview && initialIntegration ? (
        <SettingsLocalPreviewForm
          fields={fields}
          baselineValues={formBaseline}
          onApply={(values) => setPreviewValues(applyIntegrationPreview(baseline, previewValues, values))}
          onReset={() => setPreviewValues(null)}
          dirty={dirty}
        />
      ) : null}

      <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="integration-records-heading">
        <h3 id="integration-records-heading" className="text-sm font-semibold text-gray-900">
          Integration records
        </h3>
        <p className="mt-1 text-sm text-jp-muted">
          Supplier and platform integrations are shown as separate records. Sabre GDS and Sabre NDC are distinct channels
          with independent readiness metadata — no credentials displayed.
        </p>
        <ul className="mt-3 grid gap-3 lg:grid-cols-2" role="list">
          {active.integrations.map((integration) => (
            <li key={integration.key} className="rounded-lg border border-jp-border px-3 py-3 text-sm" data-testid={`integration-record-${integration.key}`}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="font-medium text-gray-900">{integration.displayName}</p>
                  <p className="text-xs text-jp-muted">{integration.key}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <ChannelBadge status={integration.channel} />
                  <CmsStatusBadge status={integration.enabled ? "valid" : "blocked"} label={integration.enabled ? "Enabled" : "Disabled"} />
                </div>
              </div>
              <dl className="mt-3 grid gap-2 sm:grid-cols-2">
                <div>
                  <dt className="text-xs text-jp-muted">Readiness</dt>
                  <dd>{integration.readinessStatus}</dd>
                </div>
                <div>
                  <dt className="text-xs text-jp-muted">Environment</dt>
                  <dd>{integration.environmentLabel}</dd>
                </div>
                <div>
                  <dt className="text-xs text-jp-muted">Completeness</dt>
                  <dd>{integration.configurationCompleteness}%</dd>
                </div>
                <div>
                  <dt className="text-xs text-jp-muted">Last check</dt>
                  <dd>{formatDateTime(integration.lastSyntheticCheck)}</dd>
                </div>
              </dl>
              <p className="mt-2 text-sm">{integration.capabilitySummary}</p>
              <p className="mt-2 text-xs text-jp-muted">
                Owner: {integration.futureOwner} · Docs: {integration.documentationReference}
              </p>
              {integration.warningState ? (
                <p className="mt-2 text-xs font-medium text-amber-800" role="status">
                  Warning state flagged in integration metadata.
                </p>
              ) : null}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
