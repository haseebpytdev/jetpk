"use client";

import { useCallback, useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  createApiConnection,
  listApiConnections,
  testApiConnection,
  toggleApiConnection,
  updateApiConnection,
} from "@/services/operational-api";

type ProviderCatalog = {
  key: string;
  label: string;
  installed: boolean;
  baseUrlOverridable: boolean;
  credentialFields: Array<{ key: string; label: string; type: string }>;
};

const FIELD_LABELS: Record<string, string> = {};

type ApiConnectionRow = {
  id: string;
  name: string;
  provider: string;
  environment: string;
  enabled: boolean;
  status?: string;
  credentialsConfigured?: boolean;
  maskedCredentials?: Record<string, string>;
  lastTestedAt?: string | null;
  lastTestStatus?: string | null;
  lastFailure?: string | null;
  sabreGdsSupported?: boolean | null;
  sabreNdcSupported?: boolean | null;
  sabreNdcEnabled?: boolean | null;
  registryLabel?: string | null;
  registryState?: string | null;
  baseUrl?: string | null;
  baseUrlOverridable?: boolean;
  credentialFields?: Array<{ key: string; label: string; type: string }>;
  audit?: { lastTestedAt?: string | null; lastTestStatus?: string | null; lastFailure?: string | null; updatedAt?: string | null };
  advanced?: { settingsKeys?: string[]; advancedBaseUrlOverride?: boolean };
};

function extractConnections(result: { ok: boolean; data?: unknown }): ApiConnectionRow[] {
  const payload = (result as { data?: { connections?: ApiConnectionRow[] }; connections?: ApiConnectionRow[] }).data
    ?? (result as { connections?: ApiConnectionRow[] });
  const rows = (payload as { connections?: ApiConnectionRow[] }).connections ?? [];
  return Array.isArray(rows) ? rows : [];
}

export function ApiConnectionsWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<ApiConnectionRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [provider, setProvider] = useState("sabre");
  const [name, setName] = useState("");
  const [environment, setEnvironment] = useState("sandbox");
  const [credentials, setCredentials] = useState<Record<string, string>>({});
  const [manageId, setManageId] = useState<string | null>(null);
  const [manageName, setManageName] = useState("");
  const [manageEnv, setManageEnv] = useState("sandbox");
  const [providers, setProviders] = useState<ProviderCatalog[]>([]);
  const [manageTab, setManageTab] = useState<"overview" | "environment" | "endpoints" | "credentials" | "capabilities" | "advanced" | "health" | "audit">("overview");
  const [manageBaseUrl, setManageBaseUrl] = useState("");

  const adapter = providers.find((item) => item.key === provider);
  const installed = Boolean(adapter?.installed);

  const refresh = useCallback(async () => {
    if (!isLive) {
      return;
    }
    const result = await listApiConnections();
    if (!result.ok) {
      setError(result.message ?? "Could not load API connections.");
      return;
    }
    setRows(extractConnections(result));
    const catalog = ((result as { data?: { providers?: ProviderCatalog[] } }).data?.providers
      ?? (result as { providers?: ProviderCatalog[] }).providers
      ?? []) as ProviderCatalog[];
    if (Array.isArray(catalog) && catalog.length > 0) {
      setProviders(catalog);
    }
  }, [isLive]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  async function run(action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusy(true);
    setError(null);
    const result = await action();
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    await refresh();
  }

  return (
    <div className="space-y-4" data-testid="api-connections-workspace">
      <p className="text-sm text-jp-muted">
        API Connections are technical channels. Suppliers remain the business vendor grouping. Secrets are never shown after save.
        Sabre GDS is integrated when SabreGdsTicketingService is installed. Sabre NDC is integrated when Offer/Order adapters exist,
        and enabled only from the connection setting (default off).
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white">
        {rows.map((row) => (
          <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 p-4 text-sm">
            <div>
              <p className="font-medium">{row.name}</p>
              <p className="text-jp-muted">
                {row.provider} · {row.environment} · {row.enabled ? "enabled" : "disabled"}
                {row.provider === "sabre"
                  ? ` · GDS ${row.sabreGdsSupported ? "supported" : "not supported"} · NDC ${
                      row.sabreNdcSupported ? "supported" : "not supported"
                    } (${row.sabreNdcEnabled ? "enabled" : "off"})`
                  : ""}
                {row.lastTestStatus ? ` · last test ${row.lastTestStatus}` : ""}
                {row.registryLabel ? ` · ${row.registryLabel}` : ""}
              </p>
            </div>
            {isLive ? (
              <div className="flex gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                  disabled={busy}
                  onClick={() => {
                    setManageId(row.id);
                    setManageName(row.name);
                    setManageEnv(row.environment || "sandbox");
                    setManageTab("overview");
                    setCredentials({});
                    setManageBaseUrl(row.baseUrl ?? "");
                  }}
                >
                  Manage
                </button>
                <button
                  type="button"
                  className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                  disabled={busy}
                  onClick={() => run(() => toggleApiConnection(String(row.id)))}
                >
                  {row.enabled ? "Disable" : "Enable"}
                </button>
                <button
                  type="button"
                  className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                  disabled={busy}
                  onClick={() => run(() => testApiConnection(String(row.id)))}
                >
                  Test
                </button>
              </div>
            ) : null}
          </li>
        ))}
      </ul>
      {manageId ? (
        <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="api-connection-manage">
          <h2 className="text-sm font-semibold">Manage connection</h2>
          {(() => {
            const row = rows.find((item) => item.id === manageId);
            if (!row) return null;
            const tabs = ["overview", "environment", "endpoints", "credentials", "capabilities", "advanced", "health", "audit"] as const;
            const fields = row.credentialFields ?? providers.find((item) => item.key === row.provider)?.credentialFields ?? [];
            return (
              <>
                <p className="text-sm">
                  {row.name} · {row.provider} · {row.registryLabel}
                </p>
                <nav className="flex flex-wrap gap-2" aria-label="Connection sections">
                  {tabs.map((tab) => (
                    <button
                      key={tab}
                      type="button"
                      className={`min-h-11 rounded-xl border px-3 text-sm capitalize ${
                        manageTab === tab ? "border-jp-accent bg-emerald-50" : "border-jp-border bg-white"
                      }`}
                      onClick={() => setManageTab(tab)}
                    >
                      {tab}
                    </button>
                  ))}
                </nav>
                {manageTab === "overview" ? (
                  <>
                    <label className="block text-xs">
                      Connection name
                      <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={manageName} onChange={(e) => setManageName(e.target.value)} />
                    </label>
                    <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                      <div><dt className="text-jp-muted">Status</dt><dd>{row.status || (row.enabled ? "enabled" : "disabled")}</dd></div>
                      <div><dt className="text-jp-muted">Registry</dt><dd>{row.registryLabel ?? "—"}</dd></div>
                      <div><dt className="text-jp-muted">Credentials</dt><dd>{row.credentialsConfigured ? "Configured (masked)" : "Not configured"}</dd></div>
                      <div><dt className="text-jp-muted">Last test</dt><dd>{row.lastTestStatus ?? "—"}</dd></div>
                    </dl>
                  </>
                ) : null}
                {manageTab === "environment" ? (
                  <label className="block text-xs">
                    Environment
                    <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={manageEnv} onChange={(e) => setManageEnv(e.target.value)}>
                      <option value="demo">demo</option>
                      <option value="sandbox">sandbox</option>
                      <option value="live">live</option>
                    </select>
                  </label>
                ) : null}
                {manageTab === "endpoints" ? (
                  row.baseUrlOverridable ? (
                    <label className="block text-xs">
                      Base URL
                      <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={manageBaseUrl} onChange={(e) => setManageBaseUrl(e.target.value)} />
                    </label>
                  ) : (
                    <p className="text-sm text-jp-muted">This adapter uses its built-in endpoint. A Base URL override is not supported.</p>
                  )
                ) : null}
                {manageTab === "credentials" ? (
                  <>
                    <p className="text-xs text-jp-muted">Stored secrets are never shown. Leave a field blank to keep the current value. Credential rotation is not executed in Owner UAT QA.</p>
                    {row.maskedCredentials
                      ? Object.entries(row.maskedCredentials).map(([key, value]) => (
                          <p key={key} className="text-xs text-jp-muted">
                            {(fields.find((field) => field.key === key)?.label ?? key)}: {value}
                          </p>
                        ))
                      : null}
                    {fields.map((field) => (
                      <label key={field.key} className="block text-xs">
                        {field.label}
                        <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" type={field.type === "password" ? "password" : "text"} autoComplete="off" value={credentials[field.key] ?? ""} onChange={(e) => setCredentials((current) => ({ ...current, [field.key]: e.target.value }))} />
                      </label>
                    ))}
                  </>
                ) : null}
                {manageTab === "capabilities" && row.provider === "sabre" ? (
                  <ul className="text-sm">
                    <li>GDS: {row.sabreGdsSupported ? "supported" : "not supported"}</li>
                    <li>NDC: {row.sabreNdcSupported ? "supported" : "not supported"} ({row.sabreNdcEnabled ? "enabled" : "off"})</li>
                  </ul>
                ) : null}
                {manageTab === "capabilities" && row.provider !== "sabre" ? (
                  <p className="text-sm text-jp-muted">Capabilities follow the installed adapter. No extra channel toggles for this provider.</p>
                ) : null}
                {manageTab === "advanced" ? (
                  <dl className="text-sm">
                    <div><dt className="text-jp-muted">Adapter settings keys</dt><dd>{(row.advanced?.settingsKeys ?? []).join(", ") || "None beyond credentials"}</dd></div>
                    <div><dt className="text-jp-muted">Sabre cancellation gates</dt><dd>{row.provider === "sabre" ? "Preserved — not editable here" : "Not applicable"}</dd></div>
                  </dl>
                ) : null}
                {manageTab === "health" ? (
                  <dl className="text-sm">
                    <div><dt className="text-jp-muted">Last tested</dt><dd>{row.lastTestedAt ?? "—"}</dd></div>
                    <div><dt className="text-jp-muted">Last status</dt><dd>{row.lastTestStatus ?? "—"}</dd></div>
                    <div><dt className="text-jp-muted">Last failure</dt><dd>{row.lastFailure ?? "—"}</dd></div>
                  </dl>
                ) : null}
                {manageTab === "audit" ? (
                  <dl className="text-sm">
                    <div><dt className="text-jp-muted">Updated</dt><dd>{row.audit?.updatedAt ?? "—"}</dd></div>
                    <div><dt className="text-jp-muted">Last tested</dt><dd>{row.audit?.lastTestedAt ?? row.lastTestedAt ?? "—"}</dd></div>
                    <div><dt className="text-jp-muted">Last status</dt><dd>{row.audit?.lastTestStatus ?? row.lastTestStatus ?? "—"}</dd></div>
                    <div><dt className="text-jp-muted">Last failure</dt><dd>{row.audit?.lastFailure ?? row.lastFailure ?? "—"}</dd></div>
                  </dl>
                ) : null}
                <div className="flex gap-2">
                  <button
                    type="button"
                    className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
                    disabled={busy}
                    onClick={() =>
                      run(() =>
                        updateApiConnection(manageId, {
                          name: manageName.trim() || row.name,
                          provider: row.provider,
                          environment: manageEnv,
                          status: row.status || (row.enabled ? "active" : "inactive"),
                          credentials: Object.fromEntries(Object.entries(credentials).filter(([, value]) => value.trim() !== "")),
                          ...(row.baseUrlOverridable ? { base_url: manageBaseUrl.trim() || null } : {}),
                        }),
                      )
                    }
                  >
                    Save connection
                  </button>
                  <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm" onClick={() => setManageId(null)}>
                    Close
                  </button>
                </div>
              </>
            );
          })()}
        </section>
      ) : null}
      <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4">
        <h2 className="text-sm font-semibold">Add connection</h2>
        <label className="block text-xs">
          Provider
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={provider} onChange={(e) => setProvider(e.target.value)}>
            {providers.map((item) => (
              <option key={item.key} value={item.key}>{item.label}{item.installed ? "" : " (not installed)"}</option>
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
            {(adapter?.credentialFields ?? []).map((field) => (
              <label key={field.key} className="block text-xs">
                {field.label}
                <input
                  className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
                  type={field.type === "password" ? "password" : "text"}
                  autoComplete="off"
                  value={credentials[field.key] ?? ""}
                  onChange={(e) => setCredentials((current) => ({ ...current, [field.key]: e.target.value }))}
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
