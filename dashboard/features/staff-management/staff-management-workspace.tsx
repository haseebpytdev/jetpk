"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { staffManagementPath } from "@/lib/api/portal-paths";

type StaffRow = {
  id: number;
  staff_code?: string;
  name?: string;
  email?: string;
  job_title?: string;
  department?: string;
  status?: string;
  assigned_bookings?: number;
};

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function StaffManagementWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<StaffRow[]>([]);
  const [kpis, setKpis] = useState<Record<string, unknown>>({});
  const [selected, setSelected] = useState<StaffRow | null>(null);
  const [search, setSearch] = useState("");
  const [error, setError] = useState<string | null>(null);

  const refresh = async () => {
    const params = new URLSearchParams();
    if (search) params.set("search", search);
    const result = await laravelRequest(staffManagementPath(params.toString()), {
      method: "GET",
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load staff.");
      return;
    }
    const payload = payloadOf(result);
    setRows(Array.isArray(payload.staff) ? (payload.staff as StaffRow[]) : []);
    setKpis((payload.kpis as Record<string, unknown>) ?? {});
    setSelected((payload.selectedStaff as StaffRow) ?? null);
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Staff" description="Available in live dashboard mode only." />
      </PageContainer>
    );
  }

  return (
    <PageContainer>
      <PageHeader title="Staff Management" description="Platform staff directory and assignment context." />
      <div className="grid gap-3 sm:grid-cols-4">
        {["total", "active", "inactive", "assigned_bookings"].map((key) => (
          <Card key={key}>
            <div className="text-xs text-jp-muted">{key}</div>
            <CardTitle className="mt-1 text-2xl">{String(kpis[key] ?? 0)}</CardTitle>
          </Card>
        ))}
      </div>
      <div className="flex flex-wrap gap-2">
        <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search staff" className="max-w-xs" />
        <Button type="button" size="sm" onClick={() => void refresh()}>
          Search
        </Button>
      </div>
      {error ? <p className="text-sm text-red-700">{error}</p> : null}
      <Card className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">Code</th>
              <th className="px-2 py-2">Name</th>
              <th className="px-2 py-2">Role</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2">Bookings</th>
              <th className="px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-b last:border-0">
                <td className="px-2 py-2 font-mono text-xs">{row.staff_code}</td>
                <td className="px-2 py-2">
                  {row.name}
                  <div className="text-xs text-jp-muted">{row.email}</div>
                </td>
                <td className="px-2 py-2">
                  {row.job_title}
                  <div className="text-xs text-jp-muted">{row.department}</div>
                </td>
                <td className="px-2 py-2">{row.status}</td>
                <td className="px-2 py-2">{row.assigned_bookings ?? 0}</td>
                <td className="px-2 py-2 text-right">
                  <Button type="button" size="sm" variant="secondary" onClick={() => setSelected(row)}>
                    Detail
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      {selected ? (
        <Card>
          <CardTitle>{selected.name}</CardTitle>
          <dl className="mt-3 grid gap-2 sm:grid-cols-2 text-sm">
            <div>
              <dt className="text-xs text-jp-muted">Email</dt>
              <dd>{selected.email}</dd>
            </div>
            <div>
              <dt className="text-xs text-jp-muted">Status</dt>
              <dd>{selected.status}</dd>
            </div>
            <div>
              <dt className="text-xs text-jp-muted">Job title</dt>
              <dd>{selected.job_title}</dd>
            </div>
            <div>
              <dt className="text-xs text-jp-muted">Department</dt>
              <dd>{selected.department}</dd>
            </div>
          </dl>
        </Card>
      ) : null}
    </PageContainer>
  );
}
