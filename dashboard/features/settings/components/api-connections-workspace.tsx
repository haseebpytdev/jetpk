"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  createApiConnection,
  listApiConnections,
  testApiConnection,
  toggleApiConnection,
  updateApiConnection,
} from "@/services/operational-api";
import { ApiConnectionCard } from "@/features/api-connections/components/connection-card";
import { AddApiConnectionCard, ProviderCatalogCards } from "@/features/api-connections/components/provider-catalog-cards";
import type { ApiConnectionRow } from "@/features/api-connections/lib/connection-status";

type FieldMeta = {
  key: string;
  label: string;
  type: string;
  required?: boolean;
  placeholder?: string;
  help?: string;
  default?: string;
  channel?: string;
  group?: string;
  options?: Array<{ value: string; label: string }>;
};

export type ProviderCatalog = {
  key: string;
  label: string;
  installed: boolean;
  baseUrlOverridable: boolean;
  credentialFields: FieldMeta[];
  advancedFields?: FieldMeta[];
};

type ProviderCardMeta = {
  key: string;
  label: string;
  channel?: string;
  description?: string;
  configured?: boolean;
  icon?: string;
  capabilities?: string[];
  readiness?: string;
};

type WorkspaceConnectionRow = ApiConnectionRow & {
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
  credentialFields?: FieldMeta[];
  audit?: {
    lastTestedAt?: string | null;
    lastTestStatus?: string | null;
    lastFailure?: string | null;
    updatedAt?: string | null;
    history?: Array<{ id?: number; action: string; createdAt?: string | null }>;
  };
  advanced?: {
    fields?: FieldMeta[];
    values?: Record<string, string>;
    timeouts?: unknown;
    timeoutsUserConfigurable?: boolean;
    baseUrlOverridable?: boolean;
    readOnly?: Array<{ key: string; label: string; value: string }>;
  };
};

function currentChannel(credentials: Record<string, string>, row?: WorkspaceConnectionRow): string {
  return credentials.api_channel || row?.advanced?.values?.api_channel || "crane_ndc";
}

function isFieldVisible(field: FieldMeta, credentials: Record<string, string>, row?: WorkspaceConnectionRow): boolean {
  if (!field.channel) {
    return true;
  }
  return field.channel === currentChannel(credentials, row);
}

function ProviderField({
  field,
  value,
  onChange,
}: {
  field: FieldMeta;
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <label className="block text-xs">
      {field.label}
      {field.required ? " *" : ""}
      {field.type === "select" && field.options && field.options.length > 0 ? (
        <select
          className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
          value={value || field.default || ""}
          onChange={(e) => onChange(e.target.value)}
        >
          {field.options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      ) : (
        <input
          className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
          type={field.type === "password" ? "password" : "text"}
          autoComplete="off"
          placeholder={field.placeholder}
          value={value}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
      {field.help ? <span className="mt-1 block text-[11px] text-jp-muted">{field.help}</span> : null}
    </label>
  );
}

function extractConnections(result: { ok: boolean; data?: unknown }): WorkspaceConnectionRow[] {
  const payload = (result as { data?: { connections?: WorkspaceConnectionRow[] }; connections?: WorkspaceConnectionRow[] }).data
    ?? (result as { connections?: WorkspaceConnectionRow[] });
  const rows = (payload as { connections?: WorkspaceConnectionRow[] }).connections ?? [];
  return Array.isArray(rows) ? rows : [];
}

export function ApiConnectionsWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<WorkspaceConnectionRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [provider, setProvider] = useState("sabre");
  const [name, setName] = useState("");
  const [environment, setEnvironment] = useState("sandbox");
  const [credentials, setCredentials] = useState<Record<string, string>>({});
  const [createBaseUrl, setCreateBaseUrl] = useState("");
  const [manageId, setManageId] = useState<string | null>(null);
  const [manageName, setManageName] = useState("");
  const [manageEnv, setManageEnv] = useState("sandbox");
  const [providers, setProviders] = useState<ProviderCatalog[]>([]);
  const [providerCards, setProviderCards] = useState<ProviderCardMeta[]>([]);
  const [showCreate, setShowCreate] = useState(false);
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
    const cards = ((result as { data?: { providerCards?: ProviderCardMeta[] } }).data?.providerCards
      ?? (result as { providerCards?: ProviderCardMeta[] }).providerCards
      ?? []) as ProviderCardMeta[];
    if (Array.isArray(cards) && cards.length > 0) {
      setProviderCards(cards);
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

  const providerLabelByKey = useMemo(() => {
    const map = new Map<string, string>();
    providers.forEach((item) => map.set(item.key, item.label));
    providerCards.forEach((item) => map.set(item.key, item.label));
    return map;
  }, [providers, providerCards]);

  const providerIconByKey = useMemo(() => {
    const map = new Map<string, string>();
    providerCards.forEach((item) => {
      if (item.icon) {
        map.set(item.key, item.icon);
      }
    });
    return map;
  }, [providerCards]);

  const connectionsByProvider = useMemo(() => {
    const grouped = new Map<string, WorkspaceConnectionRow[]>();
    rows.forEach((row) => {
      const list = grouped.get(row.provider) ?? [];
      list.push(row);
      grouped.set(row.provider, list);
    });
    return grouped;
  }, [rows]);

  return (
    <div className="space-y-6" data-testid="api-connections-workspace">
      <p className="text-sm text-jp-muted">
        Manage technical supplier channels from one hub. Business supplier records remain under Suppliers for operational reporting.
        Secrets are never shown after save.
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <section className="space-y-4" data-testid="api-connections-card-grid">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-sm font-semibold text-gray-900">Connections</h2>
          <p className="text-xs text-jp-muted">{rows.length} connection{rows.length === 1 ? "" : "s"}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {rows.map((row) => (
            <ApiConnectionCard
              key={row.id}
              row={row}
              providerLabel={providerLabelByKey.get(row.provider) ?? row.provider}
              providerIcon={providerIconByKey.get(row.provider)}
              busy={busy}
              isLive={isLive}
              onConfigure={() => {
                setManageId(row.id);
                setManageName(row.name);
                setManageEnv(row.environment || "sandbox");
                setManageTab("overview");
                setCredentials({});
                setManageBaseUrl(row.baseUrl ?? "");
                setCredentials(row.advanced?.values?.api_channel ? { api_channel: row.advanced.values.api_channel } : {});
              }}
              onTest={() => run(() => testApiConnection(String(row.id)))}
              onToggle={() => run(() => toggleApiConnection(String(row.id)))}
            />
          ))}
          <AddApiConnectionCard onClick={() => setShowCreate(true)} />
        </div>
        {connectionsByProvider.size > 0 ? (
          <div className="rounded-xl border border-jp-border bg-gray-50 p-3 text-xs text-jp-muted">
            {Array.from(connectionsByProvider.entries()).map(([providerKey, items]) => (
              <p key={providerKey}>
                {providerLabelByKey.get(providerKey) ?? providerKey}: {items.length} connection{items.length === 1 ? "" : "s"}
              </p>
            ))}
          </div>
        ) : null}
      </section>
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
                    {fields.filter((field) => isFieldVisible(field, credentials, row)).map((field) => (
                      <ProviderField
                        key={field.key}
                        field={field}
                        value={credentials[field.key] ?? (field.group === "channel" || field.group === "advanced" ? (row.advanced?.values?.[field.key] ?? field.default ?? "") : "")}
                        onChange={(value) => setCredentials((current) => ({ ...current, [field.key]: value }))}
                      />
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
                  <div className="space-y-3" data-testid="api-connection-advanced">
                    {(row.advanced?.fields ?? []).filter((field) => isFieldVisible(field, credentials, row)).map((field) => (
                      <ProviderField
                        key={field.key}
                        field={field}
                        value={credentials[field.key] ?? row.advanced?.values?.[field.key] ?? field.default ?? ""}
                        onChange={(value) => setCredentials((current) => ({ ...current, [field.key]: value }))}
                      />
                    ))}
                    {row.advanced?.timeoutsUserConfigurable ? null : (
                      <p className="text-sm text-jp-muted">Adapter timeouts are internal and not user-configurable for this provider.</p>
                    )}
                    {(row.advanced?.readOnly ?? []).map((item) => (
                      <p key={item.key} className="text-sm">
                        <span className="text-jp-muted">{item.label}: </span>
                        {item.value}
                      </p>
                    ))}
                  </div>
                ) : null}
                {manageTab === "health" ? (
                  <dl className="text-sm">
                    <div><dt className="text-jp-muted">Last tested</dt><dd>{row.lastTestedAt ?? "—"}</dd></div>
                    <div><dt className="text-jp-muted">Last status</dt><dd>{row.lastTestStatus ?? "—"}</dd></div>
                    <div><dt className="text-jp-muted">Last failure</dt><dd>{row.lastFailure ?? "—"}</dd></div>
                  </dl>
                ) : null}
                {manageTab === "audit" ? (
                  <div className="space-y-2" data-testid="api-connection-audit">
                    <p className="text-xs text-jp-muted">Audit history from AuditLog. Secret values are never shown.</p>
                    {(row.audit?.history ?? []).length === 0 ? (
                      <p className="text-sm text-jp-muted">No connection audit events yet.</p>
                    ) : (
                      <ul className="space-y-1 text-sm">
                        {(row.audit?.history ?? []).map((entry) => (
                          <li key={`${entry.action}-${entry.id ?? entry.createdAt}`}>
                            {entry.action} · {entry.createdAt ?? "unknown time"}
                          </li>
                        ))}
                      </ul>
                    )}
                    <p className="text-xs text-jp-muted">Last test: {row.audit?.lastTestStatus ?? row.lastTestStatus ?? "—"}</p>
                  </div>
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
      <section className={`space-y-3 rounded-xl border border-jp-border bg-white p-4 ${showCreate ? "" : "hidden"}`} data-testid="api-connection-create-panel">
        <div className="flex items-center justify-between gap-2">
          <h2 className="text-sm font-semibold">Add API Connection</h2>
          <button type="button" className="text-xs text-jp-muted hover:underline" onClick={() => setShowCreate(false)}>
            Close
          </button>
        </div>
        <ProviderCatalogCards
          providers={providers}
          providerCards={providerCards}
          selectedKey={provider}
          onSelect={(key) => {
            setProvider(key);
            setCredentials({});
            setCreateBaseUrl("");
          }}
        />
        <label className="block text-xs">
          Provider
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={provider} onChange={(e) => {
            setProvider(e.target.value);
            setCredentials({});
            setCreateBaseUrl("");
          }}>
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
            {adapter?.baseUrlOverridable ? (
              <label className="block text-xs">
                Base URL
                <input
                  className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
                  value={createBaseUrl}
                  onChange={(e) => setCreateBaseUrl(e.target.value)}
                  placeholder="https://api.example.com"
                />
              </label>
            ) : (
              <p className="text-xs text-jp-muted">This adapter uses its built-in endpoint. A Base URL override is not supported.</p>
            )}
            <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
              <legend className="text-sm font-medium">Credentials</legend>
              {(adapter?.credentialFields ?? []).filter((field) => isFieldVisible(field, credentials)).map((field) => (
                <ProviderField
                  key={field.key}
                  field={field}
                  value={credentials[field.key] ?? (field.type === "select" ? field.default ?? "" : "")}
                  onChange={(value) => setCredentials((current) => ({ ...current, [field.key]: value }))}
                />
              ))}
            </fieldset>
            {(adapter?.advancedFields ?? []).length > 0 ? (
              <fieldset className="space-y-2 rounded-lg border border-jp-border p-3" data-testid="api-create-advanced">
                <legend className="text-sm font-medium">Advanced configuration</legend>
                {(adapter?.advancedFields ?? []).filter((field) => isFieldVisible(field, credentials)).map((field) => (
                  <ProviderField
                    key={field.key}
                    field={field}
                    value={credentials[field.key] ?? field.default ?? ""}
                    onChange={(value) => setCredentials((current) => ({ ...current, [field.key]: value }))}
                  />
                ))}
              </fieldset>
            ) : null}
            {isLive ? (
              <button
                type="button"
                className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
                disabled={busy || !name.trim()}
                onClick={() =>
                  run(async () => {
                    const result = await createApiConnection({
                      provider,
                      name: name.trim(),
                      environment,
                      status: "inactive",
                      credentials,
                      ...(adapter?.baseUrlOverridable ? { base_url: createBaseUrl.trim() || null } : {}),
                    });
                    if (result.ok) {
                      setShowCreate(false);
                      setName("");
                      setCredentials({});
                      setCreateBaseUrl("");
                    }
                    return result;
                  })
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
