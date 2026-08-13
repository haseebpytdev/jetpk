"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { createApiConnection, testApiConnection, toggleApiConnection } from "@/services/operational-api";
import { getSuppliersPage } from "@/services/supplier-service";
import type { SupplierRecord } from "@/types/supplier";

const INSTALLED_ADAPTERS = [
  { key: "sabre", label: "Sabre", fields: ["sign_in", "password", "pcc"] },
  { key: "pia_ndc", label: "PIA NDC", fields: ["username", "password", "agency_id", "agency_name", "owner_code"] },
  { key: "airblue", label: "AirBlue", fields: ["username", "password", "agency_id"] },
  { key: "one_api", label: "One API", fields: ["api_key"] },
  { key: "duffel", label: "Duffel", fields: ["access_token"] },
];

const NOT_INSTALLED = ["amadeus", "travelport", "al_haider"];

export function ApiConnectionsWorkspace() {
  const router = useRouter();
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<SupplierRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [provider, setProvider] = useState("sabre");
  const [name, setName] = useState("");
  const [environment, setEnvironment] = useState("sandbox");
  const [credentials, setCredentials] = useState<Record<string, string>>({});

  const installed = INSTALLED_ADAPTERS.some((item) => item.key === provider);
  const adapter = INSTALLED_ADAPTERS.find((item) => item.key === provider);

  useEffect(() => {
    if (!isLive) {
      return;
    }
    void getSuppliersPage({
      q: "",
      category: "all",
      operationalStatus: "all",
      integrationStatus: "all",
      credentialStatus: "all",
      settlementStatus: "all",
      operatingRegion: "",
      hasOutstandingSettlement: "all",
      activityFrom: "",
      activityTo: "",
      page: 1,
      pageSize: 50,
      sort: "supplierName",
      direction: "asc",
      selectedId: null,
      previewError: false,
      previewLoading: false,
    }).then((result) => setRows(result.suppliers));
  }, [isLive]);

  async function run(action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusy(true);
    setError(null);
    const result = await action();
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    router.refresh();
  }

  return (
    <div className="space-y-4" data-testid="api-connections-workspace">
      <p className="text-sm text-jp-muted">
        API Connections are technical channels. Suppliers remain the business vendor grouping. Secrets are never shown after save.
        Production UAT must not rotate live credentials. Sabre NDC is shown as integrated only when Offer/Order adapters exist;
        GDS remains the default channel. NDC defaults off. Do not treat a Sabre row as NDC-capable from the provider label alone.
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white">
        {rows.map((row) => (
          <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 p-4 text-sm">
            <div>
              <p className="font-medium">{row.supplierName}</p>
              <p className="text-jp-muted">
                {row.displayCode} · {row.integrationStatus} · {row.credentialStatus} · {row.operationalStatus}
              </p>
            </div>
            {isLive ? (
              <div className="flex gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                  disabled={busy}
                  onClick={() => run(() => toggleApiConnection(row.id.replace(/^SC-0*/, "") || row.id))}
                >
                  {row.operationalStatus === "Active" ? "Disable" : "Enable"}
                </button>
                <button
                  type="button"
                  className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                  disabled={busy}
                  onClick={() => run(() => testApiConnection(row.id.replace(/^SC-0*/, "") || row.id))}
                >
                  Test
                </button>
              </div>
            ) : null}
          </li>
        ))}
      </ul>
      <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4">
        <h2 className="text-sm font-semibold">Add connection</h2>
        <label className="block text-xs">
          Provider
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={provider} onChange={(e) => setProvider(e.target.value)}>
            {INSTALLED_ADAPTERS.map((item) => (
              <option key={item.key} value={item.key}>{item.label}</option>
            ))}
            {NOT_INSTALLED.map((key) => (
              <option key={key} value={key}>{key} (not installed)</option>
            ))}
          </select>
        </label>
        {!installed ? (
          <p className="text-sm text-amber-700">Provider adapter not installed. Engineering integration required. Credential entry is disabled.</p>
        ) : (
          <>
            <label className="block text-xs">
              Connection name
              <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={name} onChange={(e) => setName(e.target.value)} />
            </label>
            <label className="block text-xs">
              Environment
              <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={environment} onChange={(e) => setEnvironment(e.target.value)}>
                <option value="demo">demo</option>
                <option value="sandbox">sandbox</option>
                <option value="live">live</option>
              </select>
            </label>
            {adapter?.fields.map((field) => (
              <label key={field} className="block text-xs">
                {field}
                <input
                  className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
                  type={field.includes("password") || field.includes("token") || field.includes("secret") ? "password" : "text"}
                  autoComplete="off"
                  value={credentials[field] ?? ""}
                  onChange={(e) => setCredentials((current) => ({ ...current, [field]: e.target.value }))}
                />
              </label>
            ))}
            {isLive ? (
              <button
                type="button"
                className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
                disabled={busy || !name.trim()}
                onClick={() =>
                  run(() =>
                    createApiConnection({
                      provider,
                      name: name.trim(),
                      environment,
                      status: "inactive",
                      credentials,
                    }),
                  )
                }
              >
                Save securely
              </button>
            ) : (
              <p className="text-xs text-jp-muted">Live credential save is available in authenticated dashboard mode only.</p>
            )}
          </>
        )}
      </section>
    </div>
  );
}
