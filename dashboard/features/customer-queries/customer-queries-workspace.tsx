"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  customerQueriesIndexPath,
  customerQueryShowPath,
  customerQueryStatusPath,
} from "@/lib/api/portal-paths";

type QueryRow = {
  id: number;
  reference?: string;
  name?: string;
  email?: string;
  status?: string;
  assigned_to?: { id: number; name: string } | null;
};

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function CustomerQueriesWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<QueryRow[]>([]);
  const [statuses, setStatuses] = useState<Array<{ value: string; label: string }>>([]);
  const [selected, setSelected] = useState<Record<string, unknown> | null>(null);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const refresh = async () => {
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (status) params.set("status", status);
    const result = await laravelRequest(customerQueriesIndexPath(params.toString()), {
      method: "GET",
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load customer queries.");
      return;
    }
    const payload = payloadOf(result);
    setRows(Array.isArray(payload.queries) ? (payload.queries as QueryRow[]) : []);
    setStatuses(
      Array.isArray(payload.statuses)
        ? (payload.statuses as Array<{ value: string; label: string }>)
        : [],
    );
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Customer Queries" description="Available in live dashboard mode only." />
      </PageContainer>
    );
  }

  return (
    <PageContainer>
      <PageHeader title="Customer Queries" description="Admin queue for customer contact inquiries." />
      <div className="flex flex-wrap gap-2">
        <Input
          value={q}
          onChange={(event) => setQ(event.target.value)}
          placeholder="Search name, email, reference"
          className="max-w-xs"
        />
        <select
          className="rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
          value={status}
          onChange={(event) => setStatus(event.target.value)}
        >
          <option value="">All statuses</option>
          {statuses.map((item) => (
            <option key={item.value} value={item.value}>
              {item.label}
            </option>
          ))}
        </select>
        <Button type="button" size="sm" onClick={() => void refresh()}>
          Filter
        </Button>
      </div>
      {error ? <p className="text-sm text-red-700">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <Card className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">Reference</th>
              <th className="px-2 py-2">Contact</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2">Assignee</th>
              <th className="px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-b last:border-0">
                <td className="px-2 py-2 font-mono text-xs">{row.reference ?? row.id}</td>
                <td className="px-2 py-2">
                  {row.name}
                  <div className="text-xs text-jp-muted">{row.email}</div>
                </td>
                <td className="px-2 py-2">{row.status}</td>
                <td className="px-2 py-2">{row.assigned_to?.name ?? "Unassigned"}</td>
                <td className="px-2 py-2 text-right">
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={busy}
                    onClick={async () => {
                      setBusy(true);
                      setError(null);
                      const result = await laravelRequest(customerQueryShowPath(row.id), {
                        method: "GET",
                        retryCsrfOnce: false,
                      });
                      setBusy(false);
                      if (!result.ok) {
                        setError(result.message ?? "Could not open query.");
                        return;
                      }
                      setSelected((payloadOf(result).query ?? null) as Record<string, unknown> | null);
                    }}
                  >
                    Open
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      {selected ? (
        <Card className="space-y-3">
          <CardTitle>{String(selected.reference ?? selected.id)}</CardTitle>
          <p className="text-sm">{String(selected.message ?? "")}</p>
          <label className="block space-y-1 text-sm">
            <span className="font-medium">Status</span>
            <select
              className="w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
              value={String(selected.status ?? "")}
              onChange={(event) => setSelected((prev) => ({ ...(prev ?? {}), status: event.target.value }))}
            >
              {statuses.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </select>
          </label>
          <label className="block space-y-1 text-sm">
            <span className="font-medium">Internal notes</span>
            <textarea
              className="min-h-24 w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm"
              value={String(selected.internal_notes ?? "")}
              onChange={(event) =>
                setSelected((prev) => ({ ...(prev ?? {}), internal_notes: event.target.value }))
              }
            />
          </label>
          <Button
            type="button"
            disabled={busy}
            onClick={async () => {
              setBusy(true);
              setError(null);
              setSuccess(null);
              const result = await laravelRequest(customerQueryStatusPath(String(selected.id)), {
                method: "PATCH",
                json: {
                  status: selected.status,
                  assigned_to_user_id: (selected.assigned_to as { id?: number } | null)?.id ?? null,
                  internal_notes: selected.internal_notes ?? "",
                },
                retryCsrfOnce: false,
              });
              setBusy(false);
              if (!result.ok) {
                setError(result.message ?? "Update failed.");
                return;
              }
              setSelected((payloadOf(result).query ?? selected) as Record<string, unknown>);
              setSuccess(("message" in result && typeof result.message === "string" ? result.message : null) ?? "Customer query updated.");
              await refresh();
            }}
          >
            Save status
          </Button>
        </Card>
      ) : null}
    </PageContainer>
  );
}
