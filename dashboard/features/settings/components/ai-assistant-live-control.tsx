"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadAiAssistantSettings, updateAiAssistantSettings } from "@/services/operational-api";

type ToggleKey =
  | "master_enabled"
  | "lab_adapter_enabled"
  | "rag_enabled"
  | "human_handoff_enabled"
  | "learning_queue_enabled"
  | "internal_canary_enabled"
  | "flight_search_read_only_enabled";

const TOGGLES: { key: ToggleKey; label: string }[] = [
  { key: "master_enabled", label: "Master enabled" },
  { key: "lab_adapter_enabled", label: "Lab adapter" },
  { key: "rag_enabled", label: "Knowledge / RAG" },
  { key: "human_handoff_enabled", label: "Human handoff" },
  { key: "learning_queue_enabled", label: "Learning queue" },
  { key: "internal_canary_enabled", label: "Internal canary audience" },
  { key: "flight_search_read_only_enabled", label: "Flight search (read-only)" },
];

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function AiAssistantLiveControl() {
  const isLive = useDashboardLiveMode();
  const [form, setForm] = useState<Record<ToggleKey, boolean>>({
    master_enabled: false,
    lab_adapter_enabled: false,
    rag_enabled: false,
    human_handoff_enabled: false,
    learning_queue_enabled: false,
    internal_canary_enabled: false,
    flight_search_read_only_enabled: false,
  });
  const [mode, setMode] = useState("off");
  const [runtimeOn, setRuntimeOn] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const applyStatus = (status: Record<string, unknown>) => {
    const admin = (status.admin && typeof status.admin === "object" ? status.admin : {}) as Record<
      string,
      unknown
    >;
    const effective = (status.effective && typeof status.effective === "object"
      ? status.effective
      : {}) as Record<string, unknown>;
    setMode(String(status.mode ?? "off"));
    setRuntimeOn(Boolean(status.runtime_on));
    setForm({
      master_enabled: Boolean(admin.master_enabled ?? effective.master_enabled),
      lab_adapter_enabled: Boolean(admin.lab_adapter_enabled ?? effective.lab_adapter_enabled),
      rag_enabled: Boolean(admin.rag_enabled ?? effective.rag_enabled),
      human_handoff_enabled: Boolean(admin.human_handoff_enabled ?? effective.human_handoff_enabled),
      learning_queue_enabled: Boolean(admin.learning_queue_enabled ?? effective.learning_queue_enabled),
      internal_canary_enabled: Boolean(status.internal_canary_enabled),
      flight_search_read_only_enabled: Boolean(
        admin.flight_search_read_only_enabled ?? effective.flight_search_read_only_enabled,
      ),
    });
  };

  useEffect(() => {
    if (!isLive) return;
    void loadAiAssistantSettings().then((result) => {
      if (!result.ok) {
        setError(result.message ?? "Could not load Ask JetPakistan settings.");
        return;
      }
      const payload = payloadOf(result); applyStatus(((payload.status as Record<string, unknown> | undefined) ?? {}) as Record<string, unknown>);
    });
  }, [isLive]);

  if (!isLive) {
    return (
      <p className="text-sm text-jp-muted">
        Ask JetPakistan settings are available in live dashboard mode only.
      </p>
    );
  }

  return (
    <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="ai-assistant-live-heading">
      <h3 id="ai-assistant-live-heading" className="text-sm font-semibold text-gray-900">
        Ask JetPakistan
      </h3>
      <p className="mt-1 text-sm text-jp-muted">
        Configures the current AI service only. Does not restore legacy orchestrators.
      </p>
      <dl className="mt-3 grid gap-3 sm:grid-cols-2">
        <div className="rounded-lg border border-jp-border px-3 py-2">
          <dt className="text-xs text-jp-muted">Mode</dt>
          <dd className="mt-1 text-sm font-medium">{mode}</dd>
        </div>
        <div className="rounded-lg border border-jp-border px-3 py-2">
          <dt className="text-xs text-jp-muted">Runtime</dt>
          <dd className="mt-1 text-sm font-medium">{runtimeOn ? "On" : "Off"}</dd>
        </div>
      </dl>
      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        {TOGGLES.map((toggle) => (
          <label key={toggle.key} className="inline-flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form[toggle.key]}
              disabled={busy}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, [toggle.key]: event.target.checked }))
              }
            />
            {toggle.label}
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
            const result = await updateAiAssistantSettings(form);
            setBusy(false);
            if (!result.ok) {
              setError(result.message ?? "Could not save Ask JetPakistan settings.");
              return;
            }
            const payload = payloadOf(result); applyStatus(((payload.status as Record<string, unknown> | undefined) ?? {}) as Record<string, unknown>);
            setSuccess(result.message ?? "Ask JetPakistan settings updated.");
          }}
        >
          Save Ask JetPakistan
        </Button>
      </div>
    </section>
  );
}
