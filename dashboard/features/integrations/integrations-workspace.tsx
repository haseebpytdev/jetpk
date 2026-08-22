"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  activateIntegration,
  deactivateIntegration,
  listIntegrations,
  showIntegration,
  testIntegrationConnection,
  testIntegrationPayment,
  updateIntegration,
} from "@/services/operational-api";

type IntegrationCard = {
  code: string;
  name: string;
  category: string;
  categoryLabel?: string;
  icon: string;
  status: string;
  status_label?: string;
  environment?: string | null;
  configured?: boolean;
  active?: boolean;
  adapterInstalled?: boolean;
  supportsConnectionTest?: boolean;
  supportsTestTransaction?: boolean;
  supportsEnableToggle?: boolean;
  canActivateRuntime?: boolean;
  docsUrl?: string | null;
  summary?: Record<string, unknown>;
  needs_attention?: boolean;
};

type HubPayload = {
  subtitle?: string;
  metrics?: { active?: number; configured?: number; needs_attention?: number; total?: number };
  categories?: Array<{ key: string; label: string }>;
  integrations?: IntegrationCard[];
  wizard?: {
    categories?: Array<{ key: string; label: string }>;
    providers?: IntegrationCard[];
    custom_api_activation_blocked?: boolean;
    custom_api_message?: string;
  };
};

const STATUS_STYLES: Record<string, string> = {
  connected: "bg-emerald-50 text-emerald-800 border-emerald-200",
  degraded: "bg-amber-50 text-amber-800 border-amber-200",
  authentication_failed: "bg-red-50 text-red-800 border-red-200",
  not_configured: "bg-slate-50 text-slate-700 border-slate-200",
  disabled: "bg-slate-100 text-slate-600 border-slate-200",
  never_tested: "bg-sky-50 text-sky-800 border-sky-200",
  adapter_missing: "bg-orange-50 text-orange-800 border-orange-200",
  draft: "bg-violet-50 text-violet-800 border-violet-200",
};

function StatusBadge({ status, label }: { status: string; label?: string }) {
  return (
    <span className={`inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide ${STATUS_STYLES[status] ?? STATUS_STYLES.not_configured}`}>
      {label ?? status.replaceAll("_", " ")}
    </span>
  );
}

export function IntegrationsWorkspace() {
  const live = useDashboardLiveMode();
  const searchParams = useSearchParams();
  const initialProvider = searchParams.get("provider");

  const [category, setCategory] = useState("all");
  const [hub, setHub] = useState<HubPayload | null>(null);
  const [permissions, setPermissions] = useState<Record<string, boolean>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<string | null>(initialProvider);
  const [detail, setDetail] = useState<Record<string, unknown> | null>(null);
  const [detailTab, setDetailTab] = useState<"overview" | "configuration" | "health" | "documentation">("overview");
  const [busy, setBusy] = useState<string | null>(null);
  const [flash, setFlash] = useState<string | null>(null);
  const [replaceSecret, setReplaceSecret] = useState(false);
  const [form, setForm] = useState<Record<string, string | boolean>>({});
  const [wizardOpen, setWizardOpen] = useState(false);
  const [wizardStep, setWizardStep] = useState(1);
  const [wizardCategory, setWizardCategory] = useState("flights");
  const [wizardProvider, setWizardProvider] = useState("");
  const [testPaymentConfirm, setTestPaymentConfirm] = useState(false);

  const loadHub = useCallback(async () => {
    setLoading(true);
    setError(null);
    const result = await listIntegrations(category);
    if (!result.ok) {
      setError(result.message || "Unable to load integrations.");
      setLoading(false);
      return;
    }
    setHub((result.data?.hub as HubPayload) ?? null);
    setPermissions((result.data?.permissions as Record<string, boolean>) ?? {});
    setLoading(false);
  }, [category]);

  const loadDetail = useCallback(async (code: string) => {
    const result = await showIntegration(code);
    if (!result.ok || !result.data?.integration) {
      setError(result.message || "Unable to load integration detail.");
      return;
    }
    const integration = result.data.integration as Record<string, unknown>;
    setDetail(integration);
    const values = ((integration.settings as { values?: Record<string, unknown> } | undefined)?.values ?? {}) as Record<string, unknown>;
    setForm({
      environment: String(values.environment ?? "test"),
      is_active: Boolean(values.is_active),
      base_url: String(values.base_url ?? ""),
      merchant_id: "",
      merchant_secret_key: "",
      success_url: String(values.success_url ?? ""),
      cancel_url: String(values.cancel_url ?? ""),
      decline_url: String(values.decline_url ?? ""),
    });
    setReplaceSecret(false);
  }, []);

  useEffect(() => {
    void loadHub();
  }, [loadHub]);

  useEffect(() => {
    if (selected) {
      void loadDetail(selected);
    } else {
      setDetail(null);
    }
  }, [selected, loadDetail]);

  const cards = hub?.integrations ?? [];
  const metrics = hub?.metrics ?? {};

  const selectedCard = useMemo(
    () => cards.find((card) => card.code === selected) ?? null,
    [cards, selected],
  );

  async function runAction(label: string, fn: () => Promise<{ ok: boolean; message?: string }>) {
    setBusy(label);
    setFlash(null);
    const result = await fn();
    setBusy(null);
    if (!result.ok) {
      setFlash(result.message || `${label} failed.`);
      return;
    }
    setFlash(`${label} completed.`);
    await loadHub();
    if (selected) {
      await loadDetail(selected);
    }
  }

  const summary = (detail?.summary as Record<string, unknown> | undefined) ?? {};
  const health = (detail?.health as { history?: Array<Record<string, unknown>>; status?: string } | undefined) ?? {};
  const isAbhiPay = selected === "abhipay";
  const envIsLive = String(summary.environment ?? form.environment ?? "") === "live";
  const secretConfigured = Boolean(summary.merchant_secret_configured);

  if (!live) {
    return (
      <div className="rounded-xl border border-jp-border bg-white p-6 text-sm text-jp-muted">
        Integrations Hub requires live dashboard mode against Laravel.
      </div>
    );
  }

  return (
    <div className="space-y-6" data-testid="integrations-hub">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-jp-ink">Integrations</h1>
          <p className="mt-1 max-w-2xl text-sm text-jp-muted">
            {hub?.subtitle ?? "Configure, test and monitor every external service connected to JetPakistan."}
          </p>
        </div>
        <button
          type="button"
          className="rounded-lg bg-jp-green px-4 py-2 text-sm font-medium text-white"
          onClick={() => {
            setWizardOpen(true);
            setWizardStep(1);
          }}
        >
          + Add Integration
        </button>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {[
          ["Active", metrics.active ?? 0],
          ["Configured", metrics.configured ?? 0],
          ["Needs attention", metrics.needs_attention ?? 0],
          ["Total", metrics.total ?? 0],
        ].map(([label, value]) => (
          <div key={String(label)} className="rounded-xl border border-jp-border bg-white px-4 py-3">
            <div className="text-xs uppercase tracking-wide text-jp-muted">{label}</div>
            <div className="mt-1 text-2xl font-semibold text-jp-ink">{value}</div>
          </div>
        ))}
      </div>

      <div className="flex flex-wrap gap-2">
        {(hub?.categories ?? [{ key: "all", label: "All" }]).map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => setCategory(tab.key)}
            className={`rounded-full border px-3 py-1 text-xs font-medium ${
              category === tab.key ? "border-jp-green bg-jp-green/10 text-jp-green" : "border-jp-border bg-white text-jp-muted"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {error ? <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div> : null}
      {flash ? <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash}</div> : null}

      {loading ? (
        <div className="text-sm text-jp-muted">Loading integrations…</div>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          {cards.map((card) => (
            <article key={card.code} className="flex flex-col rounded-xl border border-jp-border bg-white p-4 shadow-sm" data-testid={`integration-card-${card.code}`}>
              <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-jp-green/10 text-sm font-semibold text-jp-green">
                    {card.icon}
                  </div>
                  <div>
                    <h2 className="text-sm font-semibold text-jp-ink">{card.name}</h2>
                    <p className="text-xs text-jp-muted">{card.categoryLabel ?? card.category}</p>
                  </div>
                </div>
                <StatusBadge status={card.status} label={card.status_label} />
              </div>

              <div className="mt-3 space-y-1 text-xs text-jp-muted">
                {card.environment ? <div>Environment: <span className="text-jp-ink">{String(card.environment).toUpperCase()}</span></div> : null}
                <div>Configured: {card.configured ? "Yes" : "No"}</div>
                {!card.adapterInstalled ? <div className="text-amber-700">Runtime adapter not installed</div> : null}
              </div>

              <div className="mt-4 flex flex-wrap gap-2">
                <button type="button" className="rounded-md border border-jp-border px-2.5 py-1 text-xs" onClick={() => { setSelected(card.code); setDetailTab("configuration"); }}>
                  Settings
                </button>
                {card.supportsConnectionTest ? (
                  <button
                    type="button"
                    disabled={!permissions.test || busy !== null}
                    className="rounded-md border border-jp-border px-2.5 py-1 text-xs disabled:opacity-50"
                    onClick={() => void runAction("Test Connection", () => testIntegrationConnection(card.code))}
                  >
                    {busy === "Test Connection" && selected === card.code ? "Testing…" : "Test Connection"}
                  </button>
                ) : null}
                {card.docsUrl ? (
                  <a className="rounded-md border border-jp-border px-2.5 py-1 text-xs" href={card.docsUrl} target="_blank" rel="noreferrer">
                    Docs
                  </a>
                ) : null}
                {card.supportsEnableToggle && permissions.activate ? (
                  <button
                    type="button"
                    className="rounded-md border border-jp-border px-2.5 py-1 text-xs"
                    onClick={() => void runAction(card.active ? "Disable" : "Enable", () => (card.active ? deactivateIntegration(card.code) : activateIntegration(card.code)))}
                  >
                    {card.active ? "Disable" : "Enable"}
                  </button>
                ) : null}
              </div>
            </article>
          ))}
        </div>
      )}

      {selected && detail ? (
        <div className="fixed inset-0 z-40 flex justify-end bg-black/30" role="dialog" aria-modal="true">
          <div className="flex h-full w-full max-w-xl flex-col bg-white shadow-xl">
            <div className="flex items-start justify-between border-b border-jp-border px-5 py-4">
              <div>
                <h2 className="text-lg font-semibold text-jp-ink">{String(detail.name ?? selected)}</h2>
                <p className="text-xs text-jp-muted">{String(detail.categoryLabel ?? detail.category ?? "")}</p>
                <div className="mt-2"><StatusBadge status={String(detail.status ?? "")} label={String(detail.status_label ?? "")} /></div>
              </div>
              <button type="button" className="text-sm text-jp-muted" onClick={() => setSelected(null)}>Close</button>
            </div>

            <div className="flex gap-2 border-b border-jp-border px-5 py-2 text-xs">
              {(["overview", "configuration", "health", "documentation"] as const).map((tab) => (
                <button
                  key={tab}
                  type="button"
                  className={`rounded-md px-2 py-1 capitalize ${detailTab === tab ? "bg-jp-green/10 text-jp-green" : "text-jp-muted"}`}
                  onClick={() => setDetailTab(tab)}
                >
                  {tab}
                </button>
              ))}
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-4 text-sm">
              {detailTab === "overview" ? (
                <dl className="space-y-2">
                  {Object.entries(summary)
                    .filter(([key]) => !/secret|password|token|authorization/i.test(key))
                    .slice(0, 14)
                    .map(([key, value]) => (
                      <div key={key} className="grid grid-cols-2 gap-2 border-b border-jp-border/60 py-1">
                        <dt className="text-jp-muted">{key}</dt>
                        <dd className="break-all text-jp-ink">{typeof value === "object" ? JSON.stringify(value) : String(value ?? "—")}</dd>
                      </div>
                    ))}
                </dl>
              ) : null}

              {detailTab === "configuration" && isAbhiPay ? (
                <form
                  className="space-y-3"
                  onSubmit={(event) => {
                    event.preventDefault();
                    void runAction("Save settings", async () => {
                      const payload: Record<string, unknown> = {
                        environment: form.environment,
                        is_active: Boolean(form.is_active),
                        base_url: form.base_url,
                        success_url: form.success_url || undefined,
                        cancel_url: form.cancel_url || undefined,
                        decline_url: form.decline_url || undefined,
                      };
                      if (form.merchant_id) payload.merchant_id = form.merchant_id;
                      if (replaceSecret && form.merchant_secret_key) payload.merchant_secret_key = form.merchant_secret_key;
                      return updateIntegration("abhipay", payload);
                    });
                  }}
                >
                  <label className="block text-xs">Environment
                    <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5" value={String(form.environment)} onChange={(e) => setForm((f) => ({ ...f, environment: e.target.value }))}>
                      <option value="test">Test</option>
                      <option value="live">Live</option>
                    </select>
                  </label>
                  <label className="flex items-center gap-2 text-xs">
                    <input type="checkbox" checked={Boolean(form.is_active)} onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))} />
                    Active
                  </label>
                  <label className="block text-xs">Base URL
                    <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5" value={String(form.base_url)} onChange={(e) => setForm((f) => ({ ...f, base_url: e.target.value }))} />
                  </label>
                  <label className="block text-xs">Merchant ID
                    <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5" placeholder={secretConfigured ? "Leave blank to keep current" : "Merchant ID"} value={String(form.merchant_id ?? "")} onChange={(e) => setForm((f) => ({ ...f, merchant_id: e.target.value }))} autoComplete="off" />
                  </label>
                  <div className="rounded-lg border border-jp-border p-3">
                    <div className="text-xs font-medium">Merchant Secret Key</div>
                    {secretConfigured && !replaceSecret ? (
                      <div className="mt-2 flex items-center justify-between gap-2">
                        <span className="text-sm tracking-widest text-jp-muted">•••••••••• configured</span>
                        <button type="button" className="text-xs text-jp-green" onClick={() => setReplaceSecret(true)}>Replace</button>
                      </div>
                    ) : (
                      <input
                        className="mt-2 w-full rounded-lg border border-jp-border px-2 py-1.5"
                        type="password"
                        autoComplete="new-password"
                        placeholder="Enter new secret"
                        value={String(form.merchant_secret_key ?? "")}
                        onChange={(e) => setForm((f) => ({ ...f, merchant_secret_key: e.target.value }))}
                      />
                    )}
                  </div>
                  <div className="text-xs text-jp-muted">Callback URL (read-only): {String(summary.callback_url ?? "")}</div>
                  <button type="submit" disabled={!permissions.manage || busy !== null} className="rounded-lg bg-jp-green px-3 py-2 text-xs font-medium text-white disabled:opacity-50">
                    Save settings
                  </button>
                </form>
              ) : null}

              {detailTab === "configuration" && !isAbhiPay ? (
                <div className="space-y-3 text-sm text-jp-muted">
                  <p>Supplier credentials remain encrypted in Supplier Connections.</p>
                  <a className="inline-flex rounded-lg border border-jp-border px-3 py-2 text-xs text-jp-ink" href="/admin/dashboard/api-connections">
                    Open API Connections
                  </a>
                  {!detail.canActivateRuntime ? (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900" data-testid="custom-api-adapter-block">
                      Runtime activation is blocked until an approved JetPakistan adapter exists for this provider.
                    </div>
                  ) : null}
                </div>
              ) : null}

              {detailTab === "health" ? (
                <div className="space-y-3">
                  <div className="flex flex-wrap gap-2">
                    {selectedCard?.supportsConnectionTest ? (
                      <button type="button" className="rounded-md border border-jp-border px-3 py-1.5 text-xs" disabled={!permissions.test || busy !== null} onClick={() => void runAction("Test Connection", () => testIntegrationConnection(selected!))}>
                        {busy === "Test Connection" ? "Testing…" : "Test Connection"}
                      </button>
                    ) : null}
                    {selectedCard?.supportsTestTransaction ? (
                      <button
                        type="button"
                        className="rounded-md border border-jp-border px-3 py-1.5 text-xs"
                        disabled={!permissions.test_payment || busy !== null || envIsLive}
                        title={envIsLive ? "Diagnostic payments are only available in the Test environment." : undefined}
                        onClick={() => setTestPaymentConfirm(true)}
                      >
                        Test Payment
                      </button>
                    ) : null}
                  </div>
                  {envIsLive && selectedCard?.supportsTestTransaction ? (
                    <p className="text-xs text-amber-700" data-testid="live-test-payment-blocked">Diagnostic payments are only available in the Test environment.</p>
                  ) : null}
                  <ul className="space-y-2" data-testid="health-history">
                    {(health.history ?? []).map((row) => (
                      <li key={String(row.id)} className="rounded-lg border border-jp-border px-3 py-2 text-xs">
                        <div className="font-medium text-jp-ink">{String(row.test_type)} · {String(row.status)}</div>
                        <div className="text-jp-muted">{String(row.tested_at ?? "")}{row.latency_ms ? ` · ${row.latency_ms}ms` : ""}</div>
                        {row.sanitized_message ? <div className="mt-1 text-jp-muted">{String(row.sanitized_message)}</div> : null}
                      </li>
                    ))}
                    {(health.history ?? []).length === 0 ? <li className="text-jp-muted">No health history yet.</li> : null}
                  </ul>
                </div>
              ) : null}

              {detailTab === "documentation" ? (
                <div className="text-sm text-jp-muted">
                  {detail.docsUrl ? (
                    <a className="text-jp-green underline" href={String(detail.docsUrl)} target="_blank" rel="noreferrer">Open documentation</a>
                  ) : (
                    <p>No documentation reference configured.</p>
                  )}
                </div>
              ) : null}
            </div>
          </div>
        </div>
      ) : null}

      {testPaymentConfirm ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" data-testid="abhipay-test-payment-confirm">
          <div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h3 className="text-base font-semibold text-jp-ink">Confirm Test Payment</h3>
            <p className="mt-2 text-sm text-jp-muted">
              This creates a test-mode payment transaction for gateway verification. No customer booking will be used. Default amount: PKR 1.00.
            </p>
            <div className="mt-4 flex justify-end gap-2">
              <button type="button" className="rounded-md border border-jp-border px-3 py-1.5 text-xs" onClick={() => setTestPaymentConfirm(false)}>Cancel</button>
              <button
                type="button"
                className="rounded-md bg-jp-green px-3 py-1.5 text-xs text-white"
                onClick={() => {
                  setTestPaymentConfirm(false);
                  void runAction("Test Payment", () => testIntegrationPayment("abhipay", { confirm: true, amount: 1 }));
                }}
              >
                Create PKR 1.00 diagnostic
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {wizardOpen ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" data-testid="add-integration-wizard">
          <div className="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-semibold">Add Integration · Step {wizardStep}/7</h3>
              <button type="button" className="text-sm text-jp-muted" onClick={() => setWizardOpen(false)}>Close</button>
            </div>
            {wizardStep === 1 ? (
              <div className="mt-4 space-y-2" data-testid="add-integration-category">
                {(hub?.wizard?.categories ?? []).map((item) => (
                  <button key={item.key} type="button" className={`block w-full rounded-lg border px-3 py-2 text-left text-sm ${wizardCategory === item.key ? "border-jp-green bg-jp-green/5" : "border-jp-border"}`} onClick={() => setWizardCategory(item.key)}>
                    {item.label}
                  </button>
                ))}
              </div>
            ) : null}
            {wizardStep === 2 ? (
              <div className="mt-4 space-y-2" data-testid="add-integration-provider">
                {(hub?.wizard?.providers ?? [])
                  .filter((provider) => provider.category === wizardCategory)
                  .map((provider) => (
                    <button key={provider.code} type="button" className={`block w-full rounded-lg border px-3 py-2 text-left text-sm ${wizardProvider === provider.code ? "border-jp-green bg-jp-green/5" : "border-jp-border"}`} onClick={() => setWizardProvider(provider.code)}>
                      {provider.name}{!provider.adapterInstalled ? " (shell / adapter required)" : ""}
                    </button>
                  ))}
                <button type="button" className="block w-full rounded-lg border border-dashed border-jp-border px-3 py-2 text-left text-sm" onClick={() => setWizardProvider("custom_api")}>
                  Custom API
                </button>
              </div>
            ) : null}
            {wizardStep === 4 ? (
              <div className="mt-4 space-y-2 text-sm" data-testid="add-integration-auth">
                <p>Supported patterns: API key, Bearer token, Basic Auth, OAuth2, or provider-specific schema.</p>
                <p className="text-jp-muted">Credentials are stored encrypted and never returned in plaintext after save.</p>
              </div>
            ) : null}
            {wizardStep === 6 ? (
              <div className="mt-4 text-sm" data-testid="add-integration-health-test">
                Connection testing is available after the integration is registered with an approved runtime adapter.
              </div>
            ) : null}
            {wizardStep === 7 && (wizardProvider === "custom_api" || !(hub?.wizard?.providers ?? []).find((p) => p.code === wizardProvider)?.canActivateRuntime) ? (
              <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" data-testid="custom-api-adapter-block">
                {hub?.wizard?.custom_api_message ?? "A runtime adapter is required before activation."}
              </div>
            ) : null}
            <div className="mt-5 flex justify-between">
              <button type="button" className="rounded-md border border-jp-border px-3 py-1.5 text-xs" disabled={wizardStep === 1} onClick={() => setWizardStep((s) => Math.max(1, s - 1))}>Back</button>
              <button
                type="button"
                className="rounded-md bg-jp-green px-3 py-1.5 text-xs text-white"
                onClick={() => {
                  if (wizardStep < 7) {
                    setWizardStep((s) => s + 1);
                    return;
                  }
                  if (wizardProvider && wizardProvider !== "custom_api") {
                    setSelected(wizardProvider);
                    setWizardOpen(false);
                  } else {
                    setFlash("Custom API registered as draft shell only — runtime activation blocked.");
                    setWizardOpen(false);
                  }
                }}
              >
                {wizardStep === 7 ? "Finish" : "Next"}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
