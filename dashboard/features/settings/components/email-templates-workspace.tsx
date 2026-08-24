"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadMessageTemplates, resetMessageTemplate, updateMessageTemplate } from "@/services/operational-api";

type TemplateRow = {
  event: string;
  channel: string;
  category: string;
  name: string;
  has_override: boolean;
  is_enabled: boolean;
  subject: string;
  heading: string;
};

export function EmailTemplatesWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<TemplateRow[]>([]);
  const [selected, setSelected] = useState<TemplateRow | null>(null);
  const [subject, setSubject] = useState("");
  const [heading, setHeading] = useState("");
  const [body, setBody] = useState("");
  const [enabled, setEnabled] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  async function reload() {
    const result = await loadMessageTemplates();
    if (!result.ok) {
      setError(result.message ?? "Unable to load templates.");
      return;
    }
    const payload = ("data" in result ? result.data : result) as { templates?: TemplateRow[] };
    setRows(payload.templates ?? []);
  }

  useEffect(() => {
    if (!isLive) return;
    void reload();
  }, [isLive]);

  useEffect(() => {
    if (!selected) return;
    setSubject(selected.subject);
    setHeading(selected.heading);
    setBody(selected.heading || selected.subject || selected.name);
    setEnabled(selected.is_enabled);
  }, [selected]);

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="email-templates-workspace">
      <div>
        <h3 className="text-sm font-semibold text-gray-900">Email templates</h3>
        <p className="mt-1 text-xs text-jp-muted">List and edit AgencyMessageTemplateController overrides.</p>
      </div>
      {!isLive ? <p className="text-xs text-jp-muted">Available in live dashboard mode only.</p> : null}
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <ul className="max-h-80 space-y-2 overflow-auto text-sm" data-testid="email-templates-list">
          {rows.map((row) => (
            <li key={`${row.event}-${row.channel}`}>
              <button
                type="button"
                className={`w-full rounded-lg border px-3 py-2 text-left ${
                  selected?.event === row.event ? "border-jp-accent bg-emerald-50" : "border-jp-border"
                }`}
                onClick={() => setSelected(row)}
              >
                <span className="font-medium">{row.name}</span>
                <span className="mt-1 block text-xs text-jp-muted">
                  {row.category} · {row.event} · {row.has_override ? "override" : "default"}
                </span>
              </button>
            </li>
          ))}
          {rows.length === 0 ? <li className="text-jp-muted">No templates loaded.</li> : null}
        </ul>

        {selected ? (
          <div className="space-y-3" data-testid="email-template-editor">
            <label className="block text-xs font-medium text-jp-muted">
              Subject
              <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={subject} onChange={(e) => setSubject(e.target.value)} />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Heading
              <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={heading} onChange={(e) => setHeading(e.target.value)} />
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Body
              <textarea className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" rows={5} value={body} onChange={(e) => setBody(e.target.value)} />
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
              Enabled
            </label>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                className="min-h-11 rounded-xl bg-jp-accent px-4 text-sm text-white disabled:opacity-60"
                disabled={busy}
                data-testid="email-template-save"
                onClick={async () => {
                  setBusy(true);
                  setError(null);
                  setSuccess(null);
                  const result = await updateMessageTemplate(selected.event, selected.channel, {
                    subject,
                    heading,
                    body,
                    is_enabled: enabled,
                  });
                  setBusy(false);
                  if (!result.ok) {
                    setError(result.message ?? "Save failed");
                    return;
                  }
                  setSuccess("Template saved.");
                  await reload();
                }}
              >
                Save template
              </button>
              <button
                type="button"
                className="min-h-11 rounded-xl border border-jp-border px-4 text-sm disabled:opacity-60"
                disabled={busy}
                data-testid="email-template-reset"
                onClick={async () => {
                  setBusy(true);
                  setError(null);
                  setSuccess(null);
                  const result = await resetMessageTemplate(selected.event, selected.channel);
                  setBusy(false);
                  if (!result.ok) {
                    setError(result.message ?? "Reset failed");
                    return;
                  }
                  setSuccess("Template reset to default.");
                  await reload();
                }}
              >
                Reset
              </button>
            </div>
          </div>
        ) : (
          <p className="text-sm text-jp-muted">Select a template to edit.</p>
        )}
      </div>
    </section>
  );
}
