"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  agentApplicationApprovePath,
  agentApplicationNeedsInfoPath,
  agentApplicationRejectPath,
  agentApplicationsDataPath,
} from "@/lib/api/portal-paths";
import { emptyListDescription } from "@/lib/empty-list-copy";

type ApplicationRow = {
  id: string;
  name?: string;
  email?: string;
  company_name?: string;
  status?: string;
  city?: string;
  country?: string;
  document_readiness?: {
    has_cnic?: boolean;
    has_ntn?: boolean;
    has_iata?: boolean;
    has_address?: boolean;
  };
  capabilities?: {
    can_approve?: boolean;
    can_reject?: boolean;
    can_needs_more_info?: boolean;
  };
};

type Payload = {
  ok?: boolean;
  applications?: ApplicationRow[];
  kpis?: Record<string, number>;
  meta?: { page?: number; pageCount?: number; total?: number };
};

/**
 * Live agent application review against Laravel AgentApplication list/detail contracts.
 * Mutation buttons only for real selected application IDs. Production UAT must not approve/reject real applicants.
 */
export function AgencyOperationalPanel() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<ApplicationRow[]>([]);
  const [kpis, setKpis] = useState<Record<string, number>>({});
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [selected, setSelected] = useState<ApplicationRow | null>(null);
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const refresh = async () => {
    const params = new URLSearchParams();
    if (search) params.set("search", search);
    if (status) params.set("status", status);
    const result = await laravelRequest<Payload>(agentApplicationsDataPath(params.toString()), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load agent applications.");
      setRows([]);
      return;
    }
    setError(null);
    setRows(Array.isArray(result.data.applications) ? result.data.applications : []);
    setKpis(result.data.kpis ?? {});
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="agency-ops-preview">
        Agency operational actions are available in live dashboard mode only.
      </p>
    );
  }

  async function mutate(
    path: string,
    successMessage: string,
  ) {
    if (!selected?.id || busy) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await laravelRequest(path, {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      json: { internal_note: note || null },
      retryCsrfOnce: true,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Mutation failed.");
      return;
    }
    setSuccess(successMessage);
    setNote("");
    await refresh();
    setSelected(null);
  }

  return (
    <div className="space-y-3 rounded-xl border border-jp-border p-4" data-testid="agency-operational-panel">
      <h2 className="text-sm font-semibold text-gray-900">Agent applications</h2>
      <p className="text-xs text-amber-800">
        Production UAT: inspect/list only. Do not approve or reject a real applicant.
      </p>
      <div className="grid gap-2 sm:grid-cols-4">
        {["total", "pending", "approved", "rejected"].map((key) => (
          <div key={key} className="rounded-lg border border-jp-border p-2 text-sm">
            <div className="text-xs text-jp-muted">{key}</div>
            <div className="text-lg font-semibold">{kpis[key] ?? 0}</div>
          </div>
        ))}
      </div>
      <div className="flex flex-wrap gap-2">
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search applications"
          className="max-w-xs"
          data-testid="agent-app-search"
        />
        <select
          className="rounded-lg border border-jp-border px-3 py-2 text-sm"
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          data-testid="agent-app-status-filter"
        >
          <option value="">All statuses</option>
          <option value="pending">pending</option>
          <option value="approved">approved</option>
          <option value="rejected">rejected</option>
          <option value="needs_more_info">needs_more_info</option>
        </select>
        <Button type="button" size="sm" onClick={() => void refresh()}>
          Search
        </Button>
      </div>
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      {rows.length === 0 && !error ? (
        <p className="text-sm text-jp-muted">{emptyListDescription(true, "Agent applications")}</p>
      ) : null}
      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm" data-testid="agent-applications-table">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">ID</th>
              <th className="px-2 py-2">Applicant</th>
              <th className="px-2 py-2">Company</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-b last:border-0">
                <td className="px-2 py-2 font-mono text-xs">{row.id}</td>
                <td className="px-2 py-2">
                  {row.name}
                  <div className="text-xs text-jp-muted">{row.email}</div>
                </td>
                <td className="px-2 py-2">{row.company_name}</td>
                <td className="px-2 py-2">{row.status}</td>
                <td className="px-2 py-2 text-right">
                  <Button
                    type="button"
                    size="sm"
                    variant={selected?.id === row.id ? "primary" : "secondary"}
                    onClick={() => setSelected(row)}
                    data-testid={`agent-app-select-${row.id}`}
                  >
                    View
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {selected ? (
        <section className="space-y-2 rounded-lg border border-jp-border p-3" data-testid="agent-app-detail">
          <h3 className="text-sm font-semibold">
            Application {selected.id} · {selected.name}
          </h3>
          <p className="text-xs text-jp-muted">
            {selected.company_name} · {selected.city}, {selected.country} · {selected.status}
          </p>
          <p className="text-xs">
            Documents: CNIC {selected.document_readiness?.has_cnic ? "yes" : "no"} · NTN{" "}
            {selected.document_readiness?.has_ntn ? "yes" : "no"} · IATA{" "}
            {selected.document_readiness?.has_iata ? "yes" : "no"} · Address{" "}
            {selected.document_readiness?.has_address ? "yes" : "no"}
          </p>
          <Input
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Internal note (optional)"
          />
          <div className="flex flex-wrap gap-2">
            {selected.capabilities?.can_approve ? (
              <Button
                type="button"
                size="sm"
                disabled={busy}
                data-testid={`agent-app-approve-${selected.id}`}
                onClick={() =>
                  void mutate(agentApplicationApprovePath(selected.id), "Application approved.")
                }
              >
                Approve
              </Button>
            ) : null}
            {selected.capabilities?.can_reject ? (
              <Button
                type="button"
                size="sm"
                variant="secondary"
                disabled={busy}
                data-testid={`agent-app-reject-${selected.id}`}
                onClick={() =>
                  void mutate(agentApplicationRejectPath(selected.id), "Application rejected.")
                }
              >
                Reject
              </Button>
            ) : null}
            {selected.capabilities?.can_needs_more_info ? (
              <Button
                type="button"
                size="sm"
                variant="secondary"
                disabled={busy}
                data-testid={`agent-app-needs-info-${selected.id}`}
                onClick={() =>
                  void mutate(
                    agentApplicationNeedsInfoPath(selected.id),
                    "Marked needs more info.",
                  )
                }
              >
                Needs more info
              </Button>
            ) : null}
          </div>
        </section>
      ) : null}
    </div>
  );
}
