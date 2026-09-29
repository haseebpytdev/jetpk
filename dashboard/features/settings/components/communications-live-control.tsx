"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { communicationsSettingsPath } from "@/lib/api/portal-paths";

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function CommunicationsLiveControl() {
  const isLive = useDashboardLiveMode();
  const [settings, setSettings] = useState<Record<string, unknown>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const refresh = async () => {
    const result = await laravelRequest(communicationsSettingsPath(), {
      method: "GET",
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load communications settings.");
      return;
    }
    setSettings((payloadOf(result).settings as Record<string, unknown>) ?? {});
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return <p className="text-sm text-jp-muted">Communications settings are available in live mode only.</p>;
  }

  const toggleKeys: Array<{ key: string; label: string }> = [
    { key: "email_enabled", label: "Email enabled" },
    { key: "smtp_enabled", label: "SMTP enabled" },
    { key: "daily_report_enabled", label: "Daily report" },
    { key: "weekly_report_enabled", label: "Weekly report" },
    { key: "monthly_report_enabled", label: "Monthly report" },
    { key: "whatsapp_enabled", label: "WhatsApp enabled" },
  ];

  return (
    <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="communications-live-heading">
      <h3 id="communications-live-heading" className="text-sm font-semibold text-gray-900">
        Communications
      </h3>
      <p className="mt-1 text-sm text-jp-muted">
        Operational notification/channel toggles. Secrets are never displayed.
      </p>
      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        {toggleKeys.map((item) => (
          <label key={item.key} className="inline-flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={Boolean(settings[item.key])}
              disabled={busy}
              onChange={(event) =>
                setSettings((prev) => ({ ...prev, [item.key]: event.target.checked }))
              }
            />
            {item.label}
          </label>
        ))}
      </div>
      {error ? <p className="mt-2 text-sm text-red-700">{error}</p> : null}
      {success ? <p className="mt-2 text-sm text-emerald-700">{success}</p> : null}
      <div className="mt-3">
        <Button
          type="button"
          size="sm"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            setError(null);
            setSuccess(null);
            const result = await laravelRequest(communicationsSettingsPath(), {
              method: "PATCH",
              json: {
                email_enabled: Boolean(settings.email_enabled),
                smtp_enabled: Boolean(settings.smtp_enabled),
                daily_report_enabled: Boolean(settings.daily_report_enabled),
                weekly_report_enabled: Boolean(settings.weekly_report_enabled),
                monthly_report_enabled: Boolean(settings.monthly_report_enabled),
                whatsapp_enabled: Boolean(settings.whatsapp_enabled),
              },
              retryCsrfOnce: false,
            });
            setBusy(false);
            if (!result.ok) {
              setError(result.message ?? "Could not save communications settings.");
              return;
            }
            setSettings((payloadOf(result).settings as Record<string, unknown>) ?? settings);
            setSuccess("Communications settings saved.");
          }}
        >
          Save communications
        </Button>
      </div>
    </section>
  );
}
