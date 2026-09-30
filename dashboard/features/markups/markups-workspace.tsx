"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { markupStorePath, markupTogglePath, markupUpdatePath, markupsIndexPath } from "@/lib/api/portal-paths";

type MarkupRule = {
  id?: number;
  name: string;
  rule_type: string;
  value: string | number;
  value_type: string;
  priority: number;
  status: string;
  meta_notes?: string;
};

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

const emptyForm: MarkupRule = {
  name: "",
  rule_type: "route",
  value: "0",
  value_type: "percentage",
  priority: 100,
  status: "active",
  meta_notes: "",
};

export function MarkupsWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rules, setRules] = useState<MarkupRule[]>([]);
  const [kpis, setKpis] = useState<Record<string, unknown>>({});
  const [form, setForm] = useState<MarkupRule>(emptyForm);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const refresh = async () => {
    const result = await laravelRequest(markupsIndexPath(), { method: "GET", retryCsrfOnce: false });
    if (!result.ok) {
      setError(result.message ?? "Could not load markups.");
      return;
    }
    const payload = payloadOf(result);
    setRules(Array.isArray(payload.rules) ? (payload.rules as MarkupRule[]) : []);
    setKpis((payload.kpis as Record<string, unknown>) ?? {});
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

  return (
    <PageContainer>
      <PageHeader title="Markups" description="Current Laravel markup rules — UI only, no parallel pricing engine." />
      <div className="grid gap-3 sm:grid-cols-4">
        {["active", "route", "airline", "agent"].map((key) => (
          <Card key={key}>
            <div className="text-xs text-jp-muted">{key}</div>
            <CardTitle className="mt-1 text-2xl">{String(kpis[key] ?? 0)}</CardTitle>
          </Card>
        ))}
      </div>
      {error ? <p className="text-sm text-red-700">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <Card className="space-y-3">
        <CardTitle>{editingId ? `Edit rule #${editingId}` : "Create QA markup rule"}</CardTitle>
        <div className="grid gap-3 sm:grid-cols-2">
          <Input
            value={form.name}
            onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
            placeholder="Rule name"
          />
          <Input
            value={String(form.value)}
            onChange={(event) => setForm((prev) => ({ ...prev, value: event.target.value }))}
            placeholder="Value"
          />
          <select
            className="rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
            value={form.rule_type}
            onChange={(event) => setForm((prev) => ({ ...prev, rule_type: event.target.value }))}
          >
            <option value="route">Route</option>
            <option value="airline">Airline</option>
            <option value="agent">Agent</option>
          </select>
          <select
            className="rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
            value={form.value_type}
            onChange={(event) => setForm((prev) => ({ ...prev, value_type: event.target.value }))}
          >
            <option value="percentage">Percentage</option>
            <option value="fixed">Fixed</option>
          </select>
        </div>
        <Button
          type="button"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            setError(null);
            setSuccess(null);
            const payload = {
              ...form,
              meta_notes: form.meta_notes ?? "QA reversible markup",
            };
            const result = editingId
              ? await laravelRequest(markupUpdatePath(editingId), {
                  method: "PATCH",
                  json: payload,
                  retryCsrfOnce: false,
                })
              : await laravelRequest(markupStorePath(), {
                  method: "POST",
                  json: payload,
                  retryCsrfOnce: false,
                });
            setBusy(false);
            if (!result.ok) {
              setError(result.message ?? "Save failed.");
              return;
            }
            setSuccess(("message" in result && typeof result.message === "string" ? result.message : null) ?? "Markup saved.");
            setForm(emptyForm);
            setEditingId(null);
            await refresh();
          }}
        >
          {editingId ? "Update rule" : "Create rule"}
        </Button>
      </Card>
      <Card className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">Name</th>
              <th className="px-2 py-2">Type</th>
              <th className="px-2 py-2">Value</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {rules.map((rule) => (
              <tr key={rule.id} className="border-b last:border-0">
                <td className="px-2 py-2">{rule.name}</td>
                <td className="px-2 py-2">{rule.rule_type}</td>
                <td className="px-2 py-2">
                  {rule.value} ({rule.value_type})
                </td>
                <td className="px-2 py-2">{rule.status}</td>
                <td className="px-2 py-2 text-right space-x-2">
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                      setEditingId(rule.id ?? null);
                      setForm({
                        name: rule.name,
                        rule_type: rule.rule_type,
                        value: rule.value,
                        value_type: rule.value_type,
                        priority: rule.priority,
                        status: rule.status,
                        meta_notes: rule.meta_notes,
                      });
                    }}
                  >
                    Edit
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
                        retryCsrfOnce: false,
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
            ))}
          </tbody>
        </table>
      </Card>
    </PageContainer>
  );
}
