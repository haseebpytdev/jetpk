"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { SwitchToggle } from "@/components/ui/switch-toggle";
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
  module?: string;
  baseUrlOverridable: boolean;
  defaultBaseUrlSandbox?: string | null;
  defaultBaseUrlLive?: string | null;
  credentialFields: FieldMeta[];
  advancedFields?: FieldMeta[];
};

type ProviderCardMeta = {
  key: string;
  label: string;
  channel?: string;
  module?: string;
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
  module?: string;
  moduleLabel?: string;
  status?: string;
  credentialsConfigured?: boolean;
  maskedCredentials?: Record<string, string>;
  lastTestedAt?: string | null;
  lastTestStatus?: string | null;
  lastFailure?: string | null;
  lastSuccessfulUseAt?: string | null;
  sabreGdsSupported?: boolean | null;
  sabreGdsEnabled?: boolean | null;
  sabreNdcSupported?: boolean | null;
  sabreNdcEnabled?: boolean | null;
  registryLabel?: string | null;
  registryState?: string | null;
  baseUrl?: string | null;
  defaultBaseUrl?: string | null;
  baseUrlMode?: string | null;
  baseUrlOverridable?: boolean;
  credentialFields?: FieldMeta[];
  smtp?: {
    host?: string;
    port?: string;
    encryption?: string;
    from_address?: string;
    runtime_source?: string;
    username_present?: boolean;
    password_present?: boolean;
  } | null;
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

export type ApiConnectionsWorkspaceProps = {
  providerFilter?: string;
  embedded?: boolean;
  initialShowCreate?: boolean;
  onChanged?: () => void;
  /** Full API & Modules chrome (title, summary, filters). */
  showModuleChrome?: boolean;
};

const MODULE_FILTERS = [
  { key: "all", label: "All" },
  { key: "flights", label: "Flights" },
  { key: "groups", label: "Groups" },
  { key: "payments", label: "Payments" },
  { key: "hotels", label: "Hotels" },
  { key: "messaging", label: "Messaging" },
] as const;

function currentChannel(credentials: Record<string, string>, row?: WorkspaceConnectionRow): string {
  return credentials.api_channel || row?.advanced?.values?.api_channel || "crane_ndc";
}

function isFieldVisible(field: FieldMeta, credentials: Record<string, string>, row?: WorkspaceConnectionRow): boolean {
  if (!field.channel) {
    return true;
  }

  const authChannels = new Set(["manual_token", "credentials_auto_token"]);
  if (authChannels.has(field.channel)) {
    const authMode = credentials.auth_mode || row?.advanced?.values?.auth_mode || row?.maskedCredentials?.auth_mode || "manual_token";
    return field.channel === authMode;
  }

  const apiChannel = currentChannel(credentials, row);
  return field.channel === apiChannel;
}

function defaultEndpointFor(adapter: ProviderCatalog | undefined, environment: string): string {
  if (!adapter) return "";
  const live = environment === "live";
  return String((live ? adapter.defaultBaseUrlLive : adapter.defaultBaseUrlSandbox) ?? "") || "";
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

function extractMetrics(result: { ok: boolean; data?: unknown }): Record<string, number> {
  const payload = (result as { data?: { metrics?: Record<string, number> }; metrics?: Record<string, number> }).data
    ?? (result as { metrics?: Record<string, number> });
  return (payload as { metrics?: Record<string, number> }).metrics ?? {};
}

export function ApiConnectionsWorkspace({
  providerFilter,
  embedded = false,
  initialShowCreate = false,
  onChanged,
  showModuleChrome = false,
}: ApiConnectionsWorkspaceProps = {}) {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<WorkspaceConnectionRow[]>([]);
  const [metrics, setMetrics] = useState<Record<string, number>>({});
  const [moduleFilter, setModuleFilter] = useState<string>("all");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [provider, setProvider] = useState(providerFilter ?? "sabre");
  const [name, setName] = useState("");
  const [environment, setEnvironment] = useState("sandbox");
  const [credentials, setCredentials] = useState<Record<string, string>>({});
  const [createBaseUrl, setCreateBaseUrl] = useState("");
  const [createOverride, setCreateOverride] = useState(false);
  const [manageId, setManageId] = useState<string | null>(null);
  const [manageName, setManageName] = useState("");
  const [manageEnv, setManageEnv] = useState("sandbox");
  const [providers, setProviders] = useState<ProviderCatalog[]>([]);
  const [providerCards, setProviderCards] = useState<ProviderCardMeta[]>([]);
  const [showCreate, setShowCreate] = useState(Boolean(initialShowCreate));
  const [createStep, setCreateStep] = useState<"catalog" | "form">("catalog");
  const [manageTab, setManageTab] = useState<"overview" | "environment" | "endpoints" | "credentials" | "capabilities" | "advanced" | "health" | "audit">("overview");
  const [manageBaseUrl, setManageBaseUrl] = useState("");
  const [manageOverride, setManageOverride] = useState(false);
  const [manageSabreGds, setManageSabreGds] = useState(true);
  const [manageSabreNdc, setManageSabreNdc] = useState(false);

  const adapter = providers.find((item) => item.key === provider);
  const installed = Boolean(adapter?.installed);

  const refresh = useCallback(async () => {
    if (!isLive) {
      return;
    }
    const result = await listApiConnections();
    if (!result.ok) {
      setError(result.message ?? "Could not load supplier connections.");
      return;
    }
    const allRows = extractConnections(result);
    setRows(providerFilter ? allRows.filter((row) => row.provider === providerFilter) : allRows);
    setMetrics(extractMetrics(result));
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
  }, [isLive, providerFilter]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  useEffect(() => {
    if (providerFilter) {
      setProvider(providerFilter);
    }
  }, [providerFilter]);

  useEffect(() => {
    if (initialShowCreate) {
      setShowCreate(true);
      setCreateStep(providerFilter ? "form" : "catalog");
    }
  }, [initialShowCreate, providerFilter]);

  useEffect(() => {
    if (!createOverride && adapter) {
      setCreateBaseUrl(defaultEndpointFor(adapter, environment));
    }
  }, [adapter, environment, createOverride]);

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
    onChanged?.();
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

  const filteredRows = useMemo(() => {
    if (moduleFilter === "all") {
      return rows;
    }
    return rows.filter((row) => (row.module ?? "flights") === moduleFilter);
  }, [rows, moduleFilter]);

  const summary = useMemo(() => {
    if (Object.keys(metrics).length > 0) {
      return {
        configured: Number(metrics.configured ?? rows.length),
        active: Number(metrics.active ?? rows.filter((r) => r.enabled).length),
        needsAttention: Number(metrics.needs_attention ?? 0),
        modules: Number(metrics.modules ?? new Set(rows.map((r) => r.module ?? "flights")).size),
      };
    }
    return {
      configured: rows.length,
      active: rows.filter((r) => r.enabled).length,
      needsAttention: rows.filter((r) => !r.credentialsConfigured).length,
      modules: new Set(rows.map((r) => r.module ?? "flights")).size,
    };
  }, [metrics, rows]);

  function openCreate() {
    setShowCreate(true);
    setCreateStep(providerFilter ? "form" : "catalog");
    setCredentials(provider === "al_haider" ? { auth_mode: "manual_token" } : {});
    setCreateOverride(false);
    setCreateBaseUrl(defaultEndpointFor(adapter, environment));
    setError(null);
  }

  function closeCreate() {
    setShowCreate(false);
    setCreateStep("catalog");
    setName("");
    setCredentials({});
    setCreateOverride(false);
  }

  return (
    <div className="space-y-6" data-testid={embedded ? "integrations-connections-panel" : "api-connections-workspace"}>
      {showModuleChrome ? (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-jp-ink">API &amp; Modules</h1>
            <p className="mt-1 max-w-2xl text-sm text-jp-muted">
              Configured flight, group, payment, hotel, and messaging connections. Add providers from the catalog —
              unconfigured catalog entries stay out of this list.
            </p>
          </div>
          <button
            type="button"
            className="rounded-lg bg-jp-green px-4 py-2 text-sm font-medium text-white"
            onClick={openCreate}
            data-testid="api-modules-add-connection"
          >
            + Add Connection
          </button>
        </div>
      ) : embedded ? (
        <p className="text-sm text-jp-muted">
          Multiple independent connections are supported for this provider. Secrets are never shown after save.
        </p>
      ) : (
        <p className="text-sm text-jp-muted">Manage technical supplier channels. Secrets are never shown after save.</p>
      )}

      {showModuleChrome ? (
        <>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4" data-testid="api-modules-summary">
            {[
              ["Configured connections", summary.configured],
              ["Active connections", summary.active],
              ["Needs attention", summary.needsAttention],
              ["Modules / providers", summary.modules],
            ].map(([label, value]) => (
              <div key={String(label)} className="rounded-xl border border-jp-border bg-white px-4 py-3">
                <div className="text-xs uppercase tracking-wide text-jp-muted">{label}</div>
                <div className="mt-1 text-2xl font-semibold text-jp-ink">{value}</div>
              </div>
            ))}
          </div>
          <div className="flex flex-wrap gap-2" data-testid="api-modules-filters">
            {MODULE_FILTERS.map((tab) => (
              <button
                key={tab.key}
                type="button"
                onClick={() => setModuleFilter(tab.key)}
                className={`rounded-full border px-3 py-1 text-xs font-medium ${
                  moduleFilter === tab.key ? "border-jp-green bg-jp-green/10 text-jp-green" : "border-jp-border bg-white text-jp-muted"
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </>
      ) : null}

      {error ? <p className="text-sm text-red-600" data-testid="api-modules-error">{error}</p> : null}

      <section className="space-y-4" data-testid="api-connections-card-grid">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-sm font-semibold text-gray-900">
            {providerFilter ? `${providerLabelByKey.get(providerFilter) ?? providerFilter} connections` : "Configured connections"}
          </h2>
          <p className="text-xs text-jp-muted">{filteredRows.length} connection{filteredRows.length === 1 ? "" : "s"}</p>
        </div>
        {filteredRows.length === 0 && !showCreate ? (
          <div className="rounded-xl border border-dashed border-jp-border bg-white p-8 text-center" data-testid="api-modules-empty">
            <p className="text-sm font-medium text-jp-ink">No configured connections yet</p>
            <p className="mt-1 text-xs text-jp-muted">Add a connection to start configuring credentials.</p>
            <button type="button" className="mt-4 rounded-lg bg-jp-green px-4 py-2 text-sm text-white" onClick={openCreate}>
              + Add Connection
            </button>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {filteredRows.map((row) => (
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
                  setManageBaseUrl(row.baseUrl ?? row.defaultBaseUrl ?? "");
                  setManageOverride(row.baseUrlMode === "explicit_override");
                  setManageSabreGds(row.sabreGdsEnabled !== false);
                  setManageSabreNdc(Boolean(row.sabreNdcEnabled));
                  setCredentials(row.advanced?.values?.api_channel ? { api_channel: row.advanced.values.api_channel } : {});
                }}
                onTest={() => run(() => testApiConnection(String(row.id)))}
                onToggle={() => run(() => toggleApiConnection(String(row.id)))}
              />
            ))}
            {!embedded ? <AddApiConnectionCard onClick={openCreate} /> : null}
          </div>
        )}
      </section>

      {manageId ? (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/40" role="dialog" aria-modal="true" data-testid="api-connection-manage-drawer">
          <div className="flex h-full w-full max-w-xl flex-col overflow-y-auto bg-white shadow-xl">
            <div className="flex items-start justify-between border-b border-jp-border px-5 py-4">
              <h2 className="text-lg font-semibold text-jp-ink">Configure connection</h2>
              <button type="button" className="text-sm text-jp-muted" onClick={() => setManageId(null)}>
                Close
              </button>
            </div>
            <div className="space-y-3 p-5">
              {(() => {
                const row = rows.find((item) => item.id === manageId);
                if (!row) return null;
                const tabs = ["overview", "environment", "endpoints", "credentials", "capabilities", "advanced", "health", "audit"] as const;
                const fields = row.credentialFields ?? providers.find((item) => item.key === row.provider)?.credentialFields ?? [];
                const manageAdapter = providers.find((item) => item.key === row.provider);
                return (
                  <>
                    <p className="text-sm">
                      {row.name} · {row.provider} · {row.moduleLabel ?? row.module ?? "—"}
                    </p>
                    <SwitchToggle
                      id={`conn-enabled-${row.id}`}
                      label="Connection enabled"
                      description="Disable stops selection/use without deleting credentials."
                      checked={row.enabled}
                      disabled={busy}
                      onChange={() => run(() => toggleApiConnection(String(row.id)))}
                      data-testid="api-connection-enable-switch"
                    />
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
                          <div><dt className="text-jp-muted">Credentials</dt><dd>{row.credentialsConfigured ? "Configured" : "Missing"}</dd></div>
                          <div><dt className="text-jp-muted">Last health</dt><dd>{row.lastTestStatus ?? "—"}</dd></div>
                          <div><dt className="text-jp-muted">Last successful use</dt><dd>{row.lastSuccessfulUseAt ?? "—"}</dd></div>
                          {row.smtp?.runtime_source ? (
                            <div><dt className="text-jp-muted">Mail source</dt><dd>{row.smtp.runtime_source.replaceAll("_", " ")}</dd></div>
                          ) : null}
                        </dl>
                      </>
                    ) : null}
                    {manageTab === "environment" ? (
                      <label className="block text-xs">
                        Environment
                        <select
                          className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
                          value={manageEnv}
                          onChange={(e) => {
                            const next = e.target.value;
                            setManageEnv(next);
                            if (!manageOverride) {
                              setManageBaseUrl(defaultEndpointFor(manageAdapter, next) || row.defaultBaseUrl || "");
                            }
                          }}
                        >
                          <option value="demo">demo</option>
                          <option value="sandbox">sandbox / CERT</option>
                          <option value="live">live</option>
                        </select>
                      </label>
                    ) : null}
                    {manageTab === "endpoints" ? (
                      <div className="space-y-3">
                        <label className="block text-xs">
                          Standard endpoint
                          <input
                            className="mt-1 w-full rounded-lg border border-jp-border bg-slate-50 px-2 py-1"
                            value={manageOverride ? manageBaseUrl : (defaultEndpointFor(manageAdapter, manageEnv) || manageBaseUrl || row.defaultBaseUrl || "")}
                            readOnly={!manageOverride || !row.baseUrlOverridable}
                            onChange={(e) => setManageBaseUrl(e.target.value)}
                          />
                        </label>
                        {row.baseUrlOverridable ? (
                          <SwitchToggle
                            id={`endpoint-override-${row.id}`}
                            label="Use custom endpoint override"
                            description="When off, the provider default for the selected environment is used."
                            checked={manageOverride}
                            onChange={(checked) => {
                              setManageOverride(checked);
                              if (!checked) {
                                setManageBaseUrl(defaultEndpointFor(manageAdapter, manageEnv) || row.defaultBaseUrl || "");
                              }
                            }}
                          />
                        ) : (
                          <p className="text-sm text-jp-muted">This adapter uses its built-in endpoint. Override is not supported.</p>
                        )}
                      </div>
                    ) : null}
                    {manageTab === "credentials" ? (
                      <>
                        <p className="text-xs text-jp-muted">Stored secrets are never shown. Leave a field blank to keep the current value.</p>
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
                      <div className="space-y-4" data-testid="sabre-channel-toggles">
                        <p className="text-xs text-jp-muted">
                          Per-connection GDS/NDC lanes. Saving one flag does not mutate the other. Cancellation safety gates stay internal.
                        </p>
                        <SwitchToggle
                          id={`sabre-gds-${row.id}`}
                          label="Sabre GDS"
                          description={row.sabreGdsSupported ? "GDS channel for this connection" : "Adapter not installed"}
                          checked={manageSabreGds}
                          disabled={!row.sabreGdsSupported}
                          onChange={setManageSabreGds}
                          data-testid="sabre-gds-toggle"
                        />
                        <SwitchToggle
                          id={`sabre-ndc-${row.id}`}
                          label="Sabre NDC"
                          description={row.sabreNdcSupported ? "NDC channel for this connection" : "Adapter not installed"}
                          checked={manageSabreNdc}
                          disabled={!row.sabreNdcSupported}
                          onChange={setManageSabreNdc}
                          data-testid="sabre-ndc-toggle"
                        />
                        {!manageSabreGds && !manageSabreNdc ? (
                          <p className="text-xs text-amber-700">Both channels off — this connection will be skipped in search.</p>
                        ) : null}
                      </div>
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
                      </div>
                    ) : null}
                    <div className="flex gap-2 pt-2">
                      <button
                        type="button"
                        className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
                        disabled={busy}
                        onClick={() =>
                          run(async () => {
                            const result = await updateApiConnection(manageId, {
                              name: manageName.trim() || row.name,
                              provider: row.provider,
                              environment: manageEnv,
                              status: row.status || (row.enabled ? "active" : "inactive"),
                              credentials: Object.fromEntries(Object.entries(credentials).filter(([, value]) => value.trim() !== "")),
                              base_url: manageOverride ? manageBaseUrl.trim() || null : null,
                              advanced_base_url_override: manageOverride,
                              base_url_mode: manageOverride ? "explicit_override" : "provider_default",
                              ...(row.provider === "sabre"
                                ? {
                                    sabre_gds_enabled: manageSabreGds,
                                    sabre_ndc_enabled: manageSabreNdc,
                                  }
                                : {}),
                            });
                            if (result.ok) {
                              setManageId(null);
                            }
                            return result;
                          })
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
            </div>
          </div>
        </div>
      ) : null}

      {showCreate ? (
        <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center" role="dialog" aria-modal="true" data-testid="api-connection-create-modal">
          <div className="my-4 w-full max-w-3xl rounded-2xl bg-white p-5 shadow-xl">
            <div className="flex items-center justify-between gap-2">
              <h2 className="text-lg font-semibold text-jp-ink">Add connection</h2>
              <button type="button" className="text-sm text-jp-muted hover:underline" onClick={closeCreate} data-testid="api-connection-create-close">
                Close
              </button>
            </div>

            {createStep === "catalog" && !providerFilter ? (
              <div className="mt-4 space-y-3">
                <p className="text-sm text-jp-muted">Choose an installed provider. Draft / not-installed adapters are labeled clearly.</p>
                <ProviderCatalogCards
                  providers={providers}
                  providerCards={providerCards}
                  selectedKey={provider}
                  onSelect={(key) => {
                    setProvider(key);
                    setCredentials(key === "al_haider" ? { auth_mode: "manual_token" } : {});
                    setCreateOverride(false);
                    const nextAdapter = providers.find((item) => item.key === key);
                    setCreateBaseUrl(defaultEndpointFor(nextAdapter, environment));
                    setCreateStep("form");
                  }}
                />
              </div>
            ) : (
              <div className="mt-4 space-y-3" data-testid="api-connection-create-panel">
                {providerFilter ? null : (
                  <button type="button" className="text-xs text-jp-green hover:underline" onClick={() => setCreateStep("catalog")}>
                    ← Back to provider catalog
                  </button>
                )}
                <p className="text-sm font-medium text-jp-ink">Provider: {providerLabelByKey.get(provider) ?? provider}</p>
                {!installed ? (
                  <p className="text-sm text-amber-700">Provider adapter not installed / coming soon. Credential entry is disabled.</p>
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
                        <option value="sandbox">sandbox / CERT</option>
                        <option value="live">live</option>
                      </select>
                    </label>
                    {defaultEndpointFor(adapter, environment) || adapter?.baseUrlOverridable ? (
                      <div className="space-y-2">
                        <label className="block text-xs">
                          Standard endpoint
                          <input
                            className={`mt-1 w-full rounded-lg border border-jp-border px-2 py-1 ${createOverride ? "" : "bg-slate-50"}`}
                            value={createBaseUrl}
                            readOnly={!createOverride || !adapter?.baseUrlOverridable}
                            onChange={(e) => setCreateBaseUrl(e.target.value)}
                            data-testid="api-create-endpoint"
                          />
                        </label>
                        {adapter?.baseUrlOverridable ? (
                          <SwitchToggle
                            id="create-endpoint-override"
                            label="Override endpoint"
                            checked={createOverride}
                            onChange={(checked) => {
                              setCreateOverride(checked);
                              if (!checked) {
                                setCreateBaseUrl(defaultEndpointFor(adapter, environment));
                              }
                            }}
                          />
                        ) : null}
                      </div>
                    ) : null}
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
                        data-testid="api-connection-save"
                        onClick={() =>
                          run(async () => {
                            const result = await createApiConnection({
                              provider,
                              name: name.trim(),
                              environment,
                              status: "inactive",
                              credentials,
                              base_url: createOverride ? createBaseUrl.trim() || null : createBaseUrl.trim() || null,
                              advanced_base_url_override: createOverride,
                              base_url_mode: createOverride ? "explicit_override" : "provider_default",
                              ...(provider === "sabre" ? { sabre_gds_enabled: true, sabre_ndc_enabled: false } : {}),
                            });
                            if (result.ok) {
                              closeCreate();
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
              </div>
            )}
          </div>
        </div>
      ) : null}
    </div>
  );
}
