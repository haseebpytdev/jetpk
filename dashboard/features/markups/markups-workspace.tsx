"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { createMarkupRule, deleteMarkupRule, toggleMarkupRule, updateMarkupRule } from "@/services/operational-api";
import type { MarkupRecord } from "@/services/ops-modules-service";

const RULE_TYPES = ["global", "supplier", "airline", "route", "agent", "cabin", "fare_family"];
const VALUE_TYPES = ["fixed", "percentage"];
const STATUSES = ["active", "inactive"];

export function MarkupsWorkspace({ markups }: { markups: MarkupRecord[] }) {
  const router = useRouter();
  const isLive = useDashboardLiveMode();
  const [selectedId, setSelectedId] = useState<string | null>(markups[0]?.id ?? null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({
    name: "",
    rule_type: "global",
    value: "0",
    value_type: "percentage",
    priority: "100",
    status: "inactive",
    applies_to: "",
    starts_at: "",
    ends_at: "",
    meta_notes: "",
  });

  const selected = markups.find((row) => row.id === selectedId) ?? null;

  async function run(action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusy(true);
    setError(null);
    const result = await action();
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Markup request failed");
      return;
    }
    router.refresh();
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]" data-testid="markups-workspace">
      <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="markups-list">
        {markups.map((row) => (
          <li key={row.id} className="flex items-center justify-between gap-3 p-4 text-sm">
            <button type="button" className="min-w-0 text-left" onClick={() => setSelectedId(row.id)}>
              <p className="font-medium text-gray-900">{row.name}</p>
              <p className="text-jp-muted">
                {row.ruleType} · {row.value} ({row.valueType}) · priority {row.priority} · {row.status}
              </p>
            </button>
            {isLive ? (
              <div className="flex gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                  disabled={busy}
                  onClick={() => run(() => toggleMarkupRule(row.id))}
                >
                  {row.isActive ? "Disable" : "Enable"}
                </button>
                <button
                  type="button"
                  className="rounded-lg border border-red-300 px-2 py-1 text-xs text-red-700"
                  disabled={busy}
                  onClick={() => {
                    if (window.confirm(`Delete markup ${row.name}?`)) {
                      void run(() => deleteMarkupRule(row.id));
                    }
                  }}
                >
                  Delete
                </button>
              </div>
            ) : null}
          </li>
        ))}
      </ul>
      <aside className="space-y-3 rounded-xl border border-jp-border bg-white p-4">
        <h2 className="text-sm font-semibold text-gray-900">{selected ? "Edit rule" : "Create rule"}</h2>
        <p className="text-xs text-jp-muted">Uses the existing MarkupRule engine. Automated production QA must not create live rules.</p>
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        <label className="block text-xs">Name
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
        </label>
        <label className="block text-xs">Scope
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.rule_type} onChange={(e) => setForm((f) => ({ ...f, rule_type: e.target.value }))}>
            {RULE_TYPES.map((type) => (
              <option key={type} value={type}>{type}</option>
            ))}
          </select>
        </label>
        <label className="block text-xs">Method
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.value_type} onChange={(e) => setForm((f) => ({ ...f, value_type: e.target.value }))}>
            {VALUE_TYPES.map((type) => (
              <option key={type} value={type}>{type}</option>
            ))}
          </select>
        </label>
        <label className="block text-xs">Value
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.value} onChange={(e) => setForm((f) => ({ ...f, value: e.target.value }))} />
        </label>
        <label className="block text-xs">Priority
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.priority} onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))} />
        </label>
        <label className="block text-xs">Status
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}>
            {STATUSES.map((status) => (
              <option key={status} value={status}>{status}</option>
            ))}
          </select>
        </label>
        <label className="block text-xs">Applies to JSON
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.applies_to} onChange={(e) => setForm((f) => ({ ...f, applies_to: e.target.value }))} />
        </label>
        {isLive ? (
          <div className="flex flex-col gap-2">
            <button
              type="button"
              className="min-h-11 rounded-xl bg-jp-accent text-sm text-white disabled:opacity-60"
              disabled={busy}
              onClick={() =>
                run(() =>
                  createMarkupRule({
                    ...form,
                    priority: Number(form.priority),
                  }),
                )
              }
            >
              Create
            </button>
            {selected ? (
              <button
                type="button"
                className="min-h-11 rounded-xl border border-jp-border text-sm disabled:opacity-60"
                disabled={busy}
                onClick={() =>
                  run(() =>
                    updateMarkupRule(selected.id, {
                      ...form,
                      name: form.name || selected.name,
                      priority: Number(form.priority),
                    }),
                  )
                }
              >
                Save selected
              </button>
            ) : null}
          </div>
        ) : (
          <p className="text-xs text-jp-muted">Mutations are live-mode only and proven in tests, not production QA.</p>
        )}
      </aside>
    </div>
  );
}
