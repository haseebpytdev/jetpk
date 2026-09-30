"use client";

import { useEffect, useMemo, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  markupStorePath,
  markupTogglePath,
  markupUpdatePath,
  markupsIndexPath,
} from "@/lib/api/portal-paths";

type MarkupRule = {
  id?: number;
  name: string;
  rule_type: string;
  value: string | number;
  value_type: string;
  priority: number;
  status: string;
  meta_notes?: string;
  applies_to?: Record<string, unknown> | string | null;
  starts_at?: string | null;
  ends_at?: string | null;
};

type ScopeForm = {
  route: string;
  airline: string;
  supplier: string;
  agent_id: string;
  source_channel: string;
  cabin: string;
  fare_family: string;
  origin: string;
  destination: string;
};

const emptyScope = (): ScopeForm => ({
  route: "",
  airline: "",
  supplier: "",
  agent_id: "",
  source_channel: "",
  cabin: "",
  fare_family: "",
  origin: "",
  destination: "",
});

const emptyForm: MarkupRule = {
  name: "",
  rule_type: "global",
  value: "0",
  value_type: "percentage",
  priority: 100,
  status: "active",
  meta_notes: "",
  starts_at: "",
  ends_at: "",
};

const RULE_TYPES = [
  { value: "global", label: "All flights" },
  { value: "supplier", label: "Supplier / API" },
  { value: "airline", label: "Airline" },
  { value: "route", label: "Route" },
  { value: "agent", label: "Agent / Agency channel" },
  { value: "cabin", label: "Cabin" },
  { value: "fare_family", label: "Fare family" },
] as const;

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

function parseAppliesTo(raw: MarkupRule["applies_to"]): Record<string, unknown> {
  if (!raw) return {};
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw) as unknown;
      return parsed && typeof parsed === "object" ? (parsed as Record<string, unknown>) : {};
    } catch {
      return {};
    }
  }
  return typeof raw === "object" ? raw : {};
}

function buildAppliesTo(ruleType: string, scope: ScopeForm): Record<string, unknown> | null {
  switch (ruleType) {
    case "global":
      return null;
    case "route": {
      const route =
        scope.route.trim() ||
        (scope.origin && scope.destination
          ? `${scope.origin.trim().toUpperCase()}-${scope.destination.trim().toUpperCase()}`
          : "");
      return route ? { route } : null;
    }
    case "airline":
      return scope.airline.trim() ? { airline: scope.airline.trim().toUpperCase() } : null;
    case "supplier":
      return scope.supplier.trim() ? { supplier: scope.supplier.trim() } : null;
    case "agent": {
      const payload: Record<string, unknown> = {};
      if (scope.agent_id.trim()) payload.agent_id = Number(scope.agent_id) || scope.agent_id.trim();
      if (scope.source_channel.trim()) payload.source_channel = scope.source_channel.trim();
      return Object.keys(payload).length ? payload : null;
    }
    case "cabin":
      return scope.cabin.trim() ? { cabin: scope.cabin.trim() } : null;
    case "fare_family":
      return scope.fare_family.trim() ? { fare_family: scope.fare_family.trim() } : null;
    default:
      return null;
  }
}

function readablePreview(form: MarkupRule, scope: ScopeForm): string {
  const amount =
    form.value_type === "percentage"
      ? `Add ${form.value}%`
      : `Add PKR ${Number(form.value).toLocaleString("en-PK")}`;
  switch (form.rule_type) {
    case "global":
      return `${amount} to all flights`;
    case "supplier":
      return `${amount} to all ${scope.supplier || "selected supplier"} fares`;
    case "airline":
      return `${amount} to ${scope.airline || "selected airline"} fares`;
    case "route": {
      const route =
        scope.route ||
        (scope.origin && scope.destination
          ? `${scope.origin.toUpperCase()} → ${scope.destination.toUpperCase()}`
          : "selected route");
      const cabin = scope.cabin ? ` ${scope.cabin}` : "";
      return `${amount} to ${route}${cabin}`;
    }
    case "agent":
      return `${amount} for agent ${scope.agent_id || "selected agent"}${
        scope.source_channel ? ` (${scope.source_channel})` : ""
      }`;
    case "cabin":
      return `${amount} to ${scope.cabin || "selected cabin"} cabin`;
    case "fare_family":
      return `${amount} to fare family ${scope.fare_family || "selected"}`;
    default:
      return `${amount}`;
  }
}

export function MarkupsWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rules, setRules] = useState<MarkupRule[]>([]);
  const [kpis, setKpis] = useState<Record<string, unknown>>({});
  const [form, setForm] = useState<MarkupRule>(emptyForm);
  const [scope, setScope] = useState<ScopeForm>(emptyScope);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [types, setTypes] = useState<string[]>(RULE_TYPES.map((t) => t.value));

  const preview = useMemo(() => readablePreview(form, scope), [form, scope]);

  const refresh = async () => {
    const result = await laravelRequest(markupsIndexPath(), { method: "GET", retryCsrfOnce: false });
    if (!result.ok) {
      setError(result.message ?? "Could not load markups.");
      return;
    }
    const payload = payloadOf(result);
    setRules(Array.isArray(payload.rules) ? (payload.rules as MarkupRule[]) : []);
    setKpis((payload.kpis as Record<string, unknown>) ?? {});
    if (Array.isArray(payload.types) && payload.types.length) {
      setTypes(payload.types as string[]);
    }
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Markups" description="Available in live dashboard mode only." />
      </PageContainer>
    );
  }

  function loadRuleIntoForm(rule: MarkupRule) {
    const applies = parseAppliesTo(rule.applies_to);
    setEditingId(rule.id ?? null);
    setForm({
      name: rule.name,
      rule_type: rule.rule_type,
      value: rule.value,
      value_type: rule.value_type,
      priority: rule.priority,
      status: rule.status,
      meta_notes: rule.meta_notes ?? "",
      starts_at: rule.starts_at ?? "",
      ends_at: rule.ends_at ?? "",
    });
    const route = String(applies.route ?? "");
    const [origin = "", destination = ""] = route.includes("-") ? route.split("-", 2) : ["", ""];
    setScope({
      route,
      airline: String(applies.airline ?? ""),
      supplier: String(applies.supplier ?? ""),
      agent_id: applies.agent_id != null ? String(applies.agent_id) : "",
      source_channel: String(applies.source_channel ?? ""),
      cabin: String(applies.cabin ?? ""),
      fare_family: String(applies.fare_family ?? ""),
      origin,
      destination,
    });
  }

  return (
    <PageContainer>
      <PageHeader
        title="Markups"
        description="Business-readable markup rules against current MarkupRule / PricingRuleService scopes. Flight-number scope is not supported by the current engine."
      />
      <div className="grid gap-3 sm:grid-cols-4">
        {["active", "route", "airline", "agent"].map((key) => (
          <Card key={key}>
            <div className="text-xs text-jp-muted">{key}</div>
            <CardTitle className="mt-1 text-2xl">{String(kpis[key] ?? 0)}</CardTitle>
          </Card>
        ))}
      </div>
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <Card className="space-y-3" data-testid="markup-rule-builder">
        <CardTitle>{editingId ? `Edit rule #${editingId}` : "Markup business rule builder"}</CardTitle>
        <p className="text-sm text-jp-muted" data-testid="markup-readable-preview">
          Preview: {preview}
        </p>
        <p className="text-xs text-amber-800">
          Production UAT: inspect/read only unless using a fully isolated QA rule that is removed afterward.
        </p>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Name</span>
            <Input
              value={form.name}
              onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
              placeholder="Rule name"
              data-testid="markup-name"
            />
          </label>
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Amount</span>
            <Input
              value={String(form.value)}
              onChange={(event) => setForm((prev) => ({ ...prev, value: event.target.value }))}
              placeholder="Value"
              data-testid="markup-value"
            />
          </label>
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Applies to</span>
            <select
              className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
              value={form.rule_type}
              onChange={(event) => setForm((prev) => ({ ...prev, rule_type: event.target.value }))}
              data-testid="markup-rule-type"
            >
              {RULE_TYPES.filter((option) => types.includes(option.value) || types.length === 0).map(
                (option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ),
              )}
            </select>
          </label>
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Value type</span>
            <select
              className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
              value={form.value_type}
              onChange={(event) => setForm((prev) => ({ ...prev, value_type: event.target.value }))}
              data-testid="markup-value-type"
            >
              <option value="percentage">Percentage</option>
              <option value="fixed">Fixed (PKR)</option>
            </select>
          </label>
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Priority</span>
            <Input
              type="number"
              value={String(form.priority)}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, priority: Number(event.target.value) || 0 }))
              }
              data-testid="markup-priority"
            />
          </label>
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Status</span>
            <select
              className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
              value={form.status}
              onChange={(event) => setForm((prev) => ({ ...prev, status: event.target.value }))}
            >
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </label>
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Starts at</span>
            <Input
              type="datetime-local"
              value={form.starts_at ?? ""}
              onChange={(event) => setForm((prev) => ({ ...prev, starts_at: event.target.value }))}
            />
          </label>
          <label className="text-sm">
            <span className="text-xs text-jp-muted">Ends at</span>
            <Input
              type="datetime-local"
              value={form.ends_at ?? ""}
              onChange={(event) => setForm((prev) => ({ ...prev, ends_at: event.target.value }))}
            />
          </label>
        </div>

        {form.rule_type === "route" ? (
          <div className="grid gap-3 sm:grid-cols-3" data-testid="markup-scope-route">
            <Input
              value={scope.origin}
              onChange={(e) => setScope((s) => ({ ...s, origin: e.target.value }))}
              placeholder="Origin (LHE)"
            />
            <Input
              value={scope.destination}
              onChange={(e) => setScope((s) => ({ ...s, destination: e.target.value }))}
              placeholder="Destination (JED)"
            />
            <Input
              value={scope.route}
              onChange={(e) => setScope((s) => ({ ...s, route: e.target.value }))}
              placeholder="Or route LHE-JED"
            />
          </div>
        ) : null}
        {form.rule_type === "airline" ? (
          <Input
            value={scope.airline}
            onChange={(e) => setScope((s) => ({ ...s, airline: e.target.value }))}
            placeholder="Airline IATA (e.g. PK)"
            data-testid="markup-scope-airline"
          />
        ) : null}
        {form.rule_type === "supplier" ? (
          <Input
            value={scope.supplier}
            onChange={(e) => setScope((s) => ({ ...s, supplier: e.target.value }))}
            placeholder="Supplier key (e.g. sabre, hitit, zapways)"
            data-testid="markup-scope-supplier"
          />
        ) : null}
        {form.rule_type === "agent" ? (
          <div className="grid gap-3 sm:grid-cols-2" data-testid="markup-scope-agent">
            <Input
              value={scope.agent_id}
              onChange={(e) => setScope((s) => ({ ...s, agent_id: e.target.value }))}
              placeholder="Agent id"
            />
            <Input
              value={scope.source_channel}
              onChange={(e) => setScope((s) => ({ ...s, source_channel: e.target.value }))}
              placeholder="Source channel (optional)"
            />
          </div>
        ) : null}
        {form.rule_type === "cabin" ? (
          <Input
            value={scope.cabin}
            onChange={(e) => setScope((s) => ({ ...s, cabin: e.target.value }))}
            placeholder="Cabin (economy, business…)"
            data-testid="markup-scope-cabin"
          />
        ) : null}
        {form.rule_type === "fare_family" ? (
          <Input
            value={scope.fare_family}
            onChange={(e) => setScope((s) => ({ ...s, fare_family: e.target.value }))}
            placeholder="Fare family"
            data-testid="markup-scope-fare-family"
          />
        ) : null}

        <label className="block text-sm">
          <span className="text-xs text-jp-muted">Notes</span>
          <Input
            value={form.meta_notes ?? ""}
            onChange={(event) => setForm((prev) => ({ ...prev, meta_notes: event.target.value }))}
            placeholder="Internal notes"
          />
        </label>

        <Button
          type="button"
          disabled={busy}
          data-testid="markup-save"
          onClick={async () => {
            setBusy(true);
            setError(null);
            setSuccess(null);
            const appliesTo = buildAppliesTo(form.rule_type, scope);
            const payload = {
              name: form.name,
              rule_type: form.rule_type,
              value: form.value,
              value_type: form.value_type,
              priority: form.priority,
              status: form.status,
              starts_at: form.starts_at || null,
              ends_at: form.ends_at || null,
              meta_notes: form.meta_notes || "QA reversible markup",
              applies_to: appliesTo ? JSON.stringify(appliesTo) : null,
            };
            const result = editingId
              ? await laravelRequest(markupUpdatePath(editingId), {
                  method: "PATCH",
                  json: payload,
                  retryCsrfOnce: true,
                })
              : await laravelRequest(markupStorePath(), {
                  method: "POST",
                  json: payload,
                  retryCsrfOnce: true,
                });
            setBusy(false);
            if (!result.ok) {
              setError(result.message ?? "Save failed.");
              return;
            }
            setSuccess("Markup saved.");
            setForm(emptyForm);
            setScope(emptyScope());
            setEditingId(null);
            await refresh();
          }}
        >
          {editingId ? "Update rule" : "Create rule"}
        </Button>
      </Card>
      <Card className="overflow-x-auto">
        <table className="min-w-full text-left text-sm" data-testid="markup-rules-table">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">Name</th>
              <th className="px-2 py-2">Type</th>
              <th className="px-2 py-2">Scope</th>
              <th className="px-2 py-2">Value</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {rules.map((rule) => {
              const applies = parseAppliesTo(rule.applies_to);
              const scopeLabel =
                rule.rule_type === "global"
                  ? "All flights"
                  : Object.entries(applies)
                      .map(([k, v]) => `${k}=${String(v)}`)
                      .join(", ") || "—";
              return (
                <tr key={rule.id} className="border-b last:border-0">
                  <td className="px-2 py-2">{rule.name}</td>
                  <td className="px-2 py-2">{rule.rule_type}</td>
                  <td className="px-2 py-2 text-xs">{scopeLabel}</td>
                  <td className="px-2 py-2">
                    {rule.value} ({rule.value_type})
                  </td>
                  <td className="px-2 py-2">{rule.status}</td>
                  <td className="px-2 py-2 text-right space-x-2">
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      onClick={() => loadRuleIntoForm(rule)}
                    >
                      Inspect / Edit
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={busy || !rule.id}
                      onClick={async () => {
                        if (!rule.id) return;
                        setBusy(true);
                        const result = await laravelRequest(markupTogglePath(rule.id), {
                          method: "PATCH",
                          retryCsrfOnce: true,
                        });
                        setBusy(false);
                        if (!result.ok) {
                          setError(result.message ?? "Toggle failed.");
                          return;
                        }
                        await refresh();
                      }}
                    >
                      Toggle
                    </Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </Card>
    </PageContainer>
  );
}
