"use client";

import { useMemo, useState } from "react";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { validateNotificationSettings } from "@/lib/access-control/settings-validation";
import { SettingsLocalPreviewForm, type SettingsPreviewField } from "@/features/settings/components/settings-local-preview-form";
import { SettingsValidationSummary } from "@/features/settings/components/settings-validation-summary";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { useMockData } from "@/lib/preview";
import { updateNotificationCategories } from "@/services/operational-api";
import type { NotificationCategoryConfig, NotificationSettingsValues, SettingsModuleResult } from "@/types/settings-module";

function buildCategoryFields(categories: NotificationCategoryConfig[]): SettingsPreviewField[] {
  const categoryOptions = categories.map((c) => ({ value: c.key, label: c.label }));
  return [
    {
      key: "selectedCategory",
      label: "Notification category",
      type: "select",
      options: categoryOptions,
      description: "Choose a category to preview channel and delivery metadata.",
    },
    { key: "enabled", label: "Category enabled", type: "boolean" },
    { key: "emailChannel", label: "Email channel", type: "boolean" },
    { key: "dashboardChannel", label: "Dashboard channel", type: "boolean" },
    {
      key: "severityThreshold",
      label: "Severity threshold",
      type: "select",
      options: [
        { value: "notice", label: "Notice" },
        { value: "warning", label: "Warning" },
        { value: "critical", label: "Critical" },
      ],
    },
    {
      key: "deliveryMode",
      label: "Delivery mode",
      type: "select",
      options: [
        { value: "immediate", label: "Immediate" },
        { value: "digest", label: "Digest" },
      ],
    },
  ];
}

function categoryToFormValues(category: NotificationCategoryConfig): Record<string, unknown> {
  return {
    selectedCategory: category.key,
    enabled: category.enabled,
    emailChannel: category.emailChannel,
    dashboardChannel: category.dashboardChannel,
    severityThreshold: category.severityThreshold,
    deliveryMode: category.deliveryMode,
  };
}

function applyCategoryPreview(
  baseline: NotificationSettingsValues,
  preview: NotificationSettingsValues | null,
  formValues: Record<string, unknown>,
): NotificationSettingsValues {
  const source = preview ?? baseline;
  const key = String(formValues.selectedCategory ?? "");
  return {
    categories: source.categories.map((category) =>
      category.key === key
        ? {
            ...category,
            enabled: Boolean(formValues.enabled),
            emailChannel: Boolean(formValues.emailChannel),
            dashboardChannel: Boolean(formValues.dashboardChannel),
            severityThreshold: String(formValues.severityThreshold ?? category.severityThreshold),
            deliveryMode: (formValues.deliveryMode as NotificationCategoryConfig["deliveryMode"]) ?? category.deliveryMode,
          }
        : category,
    ),
  };
}

type Props = {
  result: SettingsModuleResult;
};

export function NotificationSettingsWorkspace({ result }: Props) {
  const allowLocalPreview = useMockData();
  const isLive = useDashboardLiveMode();
  const baseline = result.notifications;
  const [previewValues, setPreviewValues] = useState<NotificationSettingsValues | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const active = allowLocalPreview ? (previewValues ?? baseline) : (previewValues ?? baseline);
  const issues = useMemo(() => validateNotificationSettings(active), [active]);
  const dirty = allowLocalPreview && previewValues !== null;
  const fields = useMemo(() => buildCategoryFields(baseline.categories), [baseline.categories]);
  const initialCategory = baseline.categories[0];
  const formBaseline = initialCategory ? categoryToFormValues(initialCategory) : {};

  return (
    <div className="space-y-4" data-testid="notification-settings-workspace">
      <SettingsValidationSummary issues={issues} filter={result.query.validationState} />

      {allowLocalPreview && initialCategory ? (
        <SettingsLocalPreviewForm
          fields={fields}
          baselineValues={formBaseline}
          onApply={(values) => setPreviewValues(applyCategoryPreview(baseline, previewValues, values))}
          onReset={() => setPreviewValues(null)}
          dirty={dirty}
        />
      ) : null}

      <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="notification-categories-heading">
        <h3 id="notification-categories-heading" className="text-sm font-semibold text-gray-900">
          Notification categories
        </h3>
        <ul className="mt-3 space-y-2" role="list">
          {active.categories.map((category) => (
            <li key={category.key} className="rounded-lg border border-jp-border px-3 py-3 text-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium text-gray-900">{category.label}</p>
                <CmsStatusBadge status={category.enabled ? "valid" : "blocked"} label={category.enabled ? "Enabled" : "Disabled"} />
              </div>
              <dl className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                  <dt className="text-xs text-jp-muted">Email</dt>
                  <dd>{category.emailChannel ? "On" : "Off"}</dd>
                </div>
                <div>
                  <dt className="text-xs text-jp-muted">Dashboard</dt>
                  <dd>{category.dashboardChannel ? "On" : "Off"}</dd>
                </div>
                <div>
                  <dt className="text-xs text-jp-muted">Severity</dt>
                  <dd>{category.severityThreshold}</dd>
                </div>
                <div>
                  <dt className="text-xs text-jp-muted">Delivery</dt>
                  <dd>{category.deliveryMode}</dd>
                </div>
              </dl>
              <p className="mt-2 text-xs text-jp-muted">Recipient roles: {category.recipientRoles.join(", ")}</p>
              {isLive ? (
                <div className="mt-3 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                    onClick={() =>
                      setPreviewValues({
                        categories: active.categories.map((item) =>
                          item.key === category.key ? { ...item, enabled: !item.enabled } : item,
                        ),
                      })
                    }
                  >
                    {category.enabled ? "Disable category" : "Enable category"}
                  </button>
                  <button
                    type="button"
                    className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                    onClick={() =>
                      setPreviewValues({
                        categories: active.categories.map((item) =>
                          item.key === category.key ? { ...item, emailChannel: !item.emailChannel } : item,
                        ),
                      })
                    }
                  >
                    Toggle email
                  </button>
                  <button
                    type="button"
                    className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                    onClick={() =>
                      setPreviewValues({
                        categories: active.categories.map((item) =>
                          item.key === category.key ? { ...item, dashboardChannel: !item.dashboardChannel } : item,
                        ),
                      })
                    }
                  >
                    Toggle dashboard
                  </button>
                </div>
              ) : null}
            </li>
          ))}
        </ul>
        {isLive ? (
          <button
            type="button"
            className="mt-3 min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
            disabled={busy}
            onClick={async () => {
              setBusy(true);
              setError(null);
              setSuccess(null);
              const resultSave = await updateNotificationCategories(active.categories as unknown as Array<Record<string, unknown>>);
              setBusy(false);
              if (!resultSave.ok) {
                setError(resultSave.message ?? "Could not save notification settings");
                return;
              }
              setSuccess("Notification settings saved.");
            }}
          >
            {busy ? "Saving…" : "Save notification settings"}
          </button>
        ) : null}
        {error ? <p className="mt-2 text-sm text-red-600">{error}</p> : null}
        {success ? <p className="mt-2 text-sm text-emerald-700">{success}</p> : null}
      </section>
    </div>
  );
}
