"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { createMarkupRule, deleteMarkupRule, toggleMarkupRule, updateMarkupRule } from "@/services/operational-api";
import type { MarkupRecord } from "@/services/ops-modules-service";

const APPLY_OPTIONS = [
  { value: "global", label: "All flights" },
  { value: "supplier", label: "Supplier / API" },
  { value: "airline", label: "Airline" },
  { value: "route", label: "Route" },
  { value: "agent", label: "Agent" },
  { value: "cabin", label: "Cabin" },
  { value: "fare_family", label: "Fare family" },
] as const;

type FormState = {
  name: string;
  rule_type: string;
  value: string;
  value_type: string;
  status: string;
  supplier_key: string;
  airline_code: string;
  origin: string;
  destination: string;
  route_direction: string;
  agent_id: string;
  cabin: string;
  fare_family: string;
  showAdvanced: boolean;
  priority: string;
  starts_at: string;
  ends_at: string;
  meta_notes: string;
};

const emptyForm: FormState = {
  name: "",
  rule_type: "global",
  value: "0",
  value_type: "percentage",
  status: "inactive",
  supplier_key: "",
  airline_code: "",
  origin: "",
  destination: "",
  route_direction: "both",
  agent_id: "",
  cabin: "",
  fare_family: "",
  showAdvanced: false,
  priority: "100",
  starts_at: "",
  ends_at: "",
  meta_notes: "",
};

function preview(form: FormState): string {
  const method = form.value_type === "percentage" ? `${form.value}%` : `Rs. ${form.value}`;
  const apply = APPLY_OPTIONS.find((item) => item.value === form.rule_type)?.label ?? "All flights";
  let target = "";
  if (form.rule_type === "supplier" && form.supplier_key) target = ` (${form.supplier_key})`;
  if (form.rule_type === "airline" && form.airline_code) target = ` (${form.airline_code.toUpperCase()})`;
  if (form.rule_type === "route" && form.origin && form.destination) {
    target = ` (${form.origin.toUpperCase()} → ${form.destination.toUpperCase()}, ${form.route_direction === "one_way" ? "one way" : "both directions"})`;
  }
  if (form.rule_type === "cabin" && form.cabin) target = ` (${form.cabin})`;
  if (form.rule_type === "fare_family" && form.fare_family) target = ` (${form.fare_family})`;
  if (form.rule_type === "agent" && form.agent_id) target = ` (agent ${form.agent_id})`;
  return `${method} markup on ${apply}${target}. Starts ${form.status === "active" ? "active" : "inactive"}.`;
}

function payloadFromForm(form: FormState): Record<string, unknown> {
  return {
    name: form.name,
    rule_type: form.rule_type,
    value: form.value,
    value_type: form.value_type,
    status: form.status,
    priority: Number(form.priority),
    supplier_key: form.supplier_key,
    airline_code: form.airline_code,
    origin: form.origin,
    destination: form.destination,
    route_direction: form.route_direction,
    agent_id: form.agent_id,
    cabin: form.cabin,
    fare_family: form.fare_family,
    starts_at: form.starts_at || null,
    ends_at: form.ends_at || null,
    meta_notes: form.meta_notes,
  };
}

export function MarkupsWorkspace({ markups }: { markups: MarkupRecord[] }) {
  const router = useRouter();
  const isLive = useDashboardLiveMode();
  const [selectedId, setSelectedId] = useState<string | null>(markups[0]?.id ?? null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const selected = markups.find((row) => row.id === selectedId) ?? null;
  const previewText = useMemo(() => preview(form), [form]);

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
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]" data-testid="markups-workspace">
      <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="markups-list">
        {markups.map((row) => (
          <li key={row.id} className="flex items-center justify-between gap-3 p-4 text-sm">
            <button type="button" className="min-w-0 text-left" onClick={() => setSelectedId(row.id)}>
              <p className="font-medium text-gray-900">{row.name}</p>
              <p className="text-jp-muted">
                {APPLY_OPTIONS.find((item) => item.value === row.ruleType)?.label ?? row.ruleType}
                {" · "}
                {row.valueType === "percentage" ? `${row.value}%` : `Rs. ${row.value}`}
                {" · "}
                {row.status}
              </p>
            </button>
            {isLive ? (
              <div className="flex gap-2">
                <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" disabled={busy} onClick={() => run(() => toggleMarkupRule(row.id))}>
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
        <h2 className="text-sm font-semibold text-gray-900">{selected ? "Edit markup" : "Create markup"}</h2>
        <p className="text-xs text-jp-muted">Business controls map to the existing MarkupRule engine. Production QA must not create live commercial rules.</p>
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        <label className="block text-xs">Name
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
        </label>
        <label className="block text-xs">Apply to
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.rule_type} onChange={(e) => setForm((f) => ({ ...f, rule_type: e.target.value }))}>
            {APPLY_OPTIONS.map((item) => (
              <option key={item.value} value={item.value}>{item.label}</option>
            ))}
          </select>
        </label>
        {form.rule_type === "supplier" ? (
          <label className="block text-xs">Supplier / API
            <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" placeholder="Sabre, PIA NDC…" value={form.supplier_key} onChange={(e) => setForm((f) => ({ ...f, supplier_key: e.target.value }))} />
          </label>
        ) : null}
        {form.rule_type === "airline" ? (
          <label className="block text-xs">Airline code or name
            <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" placeholder="PK" value={form.airline_code} onChange={(e) => setForm((f) => ({ ...f, airline_code: e.target.value }))} />
          </label>
        ) : null}
        {form.rule_type === "route" ? (
          <div className="grid grid-cols-2 gap-2">
            <label className="block text-xs">Origin
              <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" placeholder="LHE" value={form.origin} onChange={(e) => setForm((f) => ({ ...f, origin: e.target.value }))} />
            </label>
            <label className="block text-xs">Destination
              <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" placeholder="DXB" value={form.destination} onChange={(e) => setForm((f) => ({ ...f, destination: e.target.value }))} />
            </label>
            <label className="col-span-2 block text-xs">Direction
              <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.route_direction} onChange={(e) => setForm((f) => ({ ...f, route_direction: e.target.value }))}>
                <option value="both">Both directions</option>
                <option value="one_way">One way</option>
              </select>
            </label>
          </div>
        ) : null}
        {form.rule_type === "agent" ? (
          <label className="block text-xs">Agent ID
            <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.agent_id} onChange={(e) => setForm((f) => ({ ...f, agent_id: e.target.value }))} />
          </label>
        ) : null}
        {form.rule_type === "cabin" ? (
          <label className="block text-xs">Cabin
            <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" placeholder="economy" value={form.cabin} onChange={(e) => setForm((f) => ({ ...f, cabin: e.target.value }))} />
          </label>
        ) : null}
        {form.rule_type === "fare_family" ? (
          <label className="block text-xs">Fare family
            <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.fare_family} onChange={(e) => setForm((f) => ({ ...f, fare_family: e.target.value }))} />
          </label>
        ) : null}
        <label className="block text-xs">Method
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.value_type} onChange={(e) => setForm((f) => ({ ...f, value_type: e.target.value }))}>
            <option value="percentage">Percentage</option>
            <option value="fixed">Fixed amount</option>
          </select>
        </label>
        <label className="block text-xs">Value
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.value} onChange={(e) => setForm((f) => ({ ...f, value: e.target.value }))} />
        </label>
        <p className="rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-900" data-testid="markup-rule-preview">{previewText}</p>
        <button type="button" className="text-xs text-jp-accent" onClick={() => setForm((f) => ({ ...f, showAdvanced: !f.showAdvanced }))}>
          {form.showAdvanced ? "Hide advanced" : "Advanced (priority, dates)"}
        </button>
        {form.showAdvanced ? (
          <div className="space-y-2 rounded-lg border border-jp-border p-3">
            <label className="block text-xs">Priority
              <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.priority} onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))} />
            </label>
            <label className="block text-xs">Starts
              <input type="date" className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.starts_at} onChange={(e) => setForm((f) => ({ ...f, starts_at: e.target.value }))} />
            </label>
            <label className="block text-xs">Ends
              <input type="date" className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={form.ends_at} onChange={(e) => setForm((f) => ({ ...f, ends_at: e.target.value }))} />
            </label>
          </div>
        ) : null}
        {isLive ? (
          <div className="flex flex-col gap-2">
            <button type="button" className="min-h-11 rounded-xl bg-jp-accent text-sm text-white disabled:opacity-60" disabled={busy} onClick={() => run(() => createMarkupRule(payloadFromForm(form)))}>
              Create
            </button>
            {selected ? (
              <button type="button" className="min-h-11 rounded-xl border border-jp-border text-sm disabled:opacity-60" disabled={busy} onClick={() => run(() => updateMarkupRule(selected.id, { ...payloadFromForm(form), name: form.name || selected.name }))}>
                Save selected
              </button>
            ) : null}
          </div>
        ) : (
          <p className="text-xs text-jp-muted">Mutations are live-mode only and proven in tests, not production commercial QA.</p>
        )}
      </aside>
    </div>
  );
}
