"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadPageSettings, publishPageSettings, savePageSettings } from "@/services/operational-api";

export function HomepageSettingsPanel() {
  const isLive = useDashboardLiveMode();
  const [json, setJson] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    if (!isLive) {
      return;
    }
    void loadPageSettings("home").then((result) => {
      if (!result.ok) {
        setError(result.message ?? "Could not load homepage settings.");
        return;
      }
      const payload = ("data" in result ? result.data : result) as { content?: Record<string, unknown> };
      setJson(JSON.stringify(payload.content ?? {}, null, 2));
    });
  }, [isLive]);

  if (!isLive) {
    return <p className="text-xs text-jp-muted">Homepage Page Settings are available in live dashboard mode only.</p>;
  }

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="homepage-settings-panel">
      <h2 className="text-sm font-semibold">Homepage (live Page Settings)</h2>
      <p className="text-xs text-jp-muted">
        This edits the published JetPakistan homepage draft in client_page_settings. Save draft, then publish. Does not
        invent a page builder.
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <textarea
        className="min-h-64 w-full rounded-lg border border-jp-border p-2 font-mono text-xs"
        value={json}
        onChange={(e) => setJson(e.target.value)}
        data-testid="homepage-settings-json"
      />
      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            setError(null);
            setSuccess(null);
            try {
              const content = JSON.parse(json) as Record<string, unknown>;
              const result = await savePageSettings("home", content);
              if (!result.ok) {
                setError(result.message ?? "Save failed");
              } else {
                setSuccess("Homepage draft saved.");
              }
            } catch {
              setError("Content must be valid JSON.");
            }
            setBusy(false);
          }}
        >
          {busy ? "Saving…" : "Save draft"}
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            setError(null);
            setSuccess(null);
            const result = await publishPageSettings("home");
            setBusy(false);
            if (!result.ok) {
              setError(result.message ?? "Publish failed");
              return;
            }
            setSuccess("Homepage published.");
          }}
        >
          Publish
        </button>
      </div>
    </section>
  );
}
