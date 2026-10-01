"use client";

import { useEffect, useId, useRef, useState } from "react";
import { IconButton } from "@/components/ui/icon-button";
import { ProviderCatalogCards } from "@/features/api-connections/components/provider-catalog-cards";
import {
  airBlueEndpointPreview,
  airBlueSuggestedConnectionName,
  AIRBLUE_MINIMAL_FIELD_KEYS,
  isAirBlueAutoConnectionName,
} from "@/features/api-connections/lib/airblue-zapways-contract";
import type { ProviderCatalog } from "@/features/settings/components/api-connections-workspace";

type FieldMeta = ProviderCatalog["credentialFields"][number];

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

type Props = {
  open: boolean;
  onClose: () => void;
  providers: ProviderCatalog[];
  providerCards: ProviderCardMeta[];
  isLive: boolean;
  busy: boolean;
  error: string | null;
  onSave: (payload: {
    provider: string;
    name: string;
    environment: string;
    credentials: Record<string, string>;
    base_url?: string | null;
  }) => Promise<{ ok: boolean; message?: string }>;
};

function isFieldVisible(field: FieldMeta, credentials: Record<string, string>): boolean {
  if (!field.channel) {
    return true;
  }
  const authChannels = new Set(["manual_token", "credentials_auto_token"]);
  if (authChannels.has(field.channel)) {
    const authMode = credentials.auth_mode || "manual_token";
    return field.channel === authMode;
  }
  const apiChannel = credentials.api_channel || "zapways_ota";
  return field.channel === apiChannel;
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
          data-testid={`api-create-field-${field.key}`}
        />
      )}
      {field.help ? <span className="mt-1 block text-[11px] text-jp-muted">{field.help}</span> : null}
    </label>
  );
}

function AirBlueEndpointPreview({ environment }: { environment: string }) {
  const endpoint = airBlueEndpointPreview(environment);
  const envLabel = environment === "live" ? "Live" : "Test";

  return (
    <div className="rounded-lg border border-jp-border bg-gray-50 p-3 text-xs" data-testid="airblue-endpoint-preview">
      <p className="font-medium text-gray-900">Environment: {envLabel}</p>
      <p className="mt-2 text-jp-muted">Endpoint</p>
      <p className="font-mono text-[11px] text-gray-900">{endpoint}</p>
      <p className="mt-2 text-jp-muted">Protocol</p>
      <p>Zapways OTA v2</p>
      <p className="mt-2 text-jp-muted">mTLS</p>
      <p>JetPakistan certificate configured</p>
    </div>
  );
}

export function AddApiConnectionModal({
  open,
  onClose,
  providers,
  providerCards,
  isLive,
  busy,
  error,
  onSave,
}: Props) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const addButtonRef = useRef<HTMLElement | null>(null);
  const [step, setStep] = useState<"pick" | "configure">("pick");
  const [provider, setProvider] = useState("");
  const [name, setName] = useState("");
  const [environment, setEnvironment] = useState("sandbox");
  const [credentials, setCredentials] = useState<Record<string, string>>({});
  const [createBaseUrl, setCreateBaseUrl] = useState("");
  const [localError, setLocalError] = useState<string | null>(null);

  const adapter = providers.find((item) => item.key === provider);
  const installed = Boolean(adapter?.installed);
  const cardMeta = providerCards.find((item) => item.key === provider);
  const isAirBlue = provider === "airblue";

  const resetState = () => {
    setStep("pick");
    setProvider("");
    setName("");
    setEnvironment("sandbox");
    setCredentials({});
    setCreateBaseUrl("");
    setLocalError(null);
  };

  useEffect(() => {
    if (!open) {
      return;
    }
    addButtonRef.current = document.querySelector('[data-testid="api-connection-add-card"]') as HTMLElement | null;
    resetState();
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }
    const prev = document.activeElement as HTMLElement | null;
    const focusTimer = window.setTimeout(() => {
      panelRef.current?.focus();
    }, 0);
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape" && !busy) {
        event.preventDefault();
        event.stopPropagation();
        onClose();
      }
    };
    document.addEventListener("keydown", onKey, true);
    document.body.style.overflow = "hidden";
    return () => {
      window.clearTimeout(focusTimer);
      document.removeEventListener("keydown", onKey, true);
      document.body.style.overflow = "";
      if (!open) {
        addButtonRef.current?.focus();
      } else {
        prev?.focus();
      }
    };
  }, [open, busy, onClose]);

  useEffect(() => {
    if (!isAirBlue) {
      return;
    }
    setName((current) => {
      if (current.trim() === "" || isAirBlueAutoConnectionName(current)) {
        return airBlueSuggestedConnectionName(environment);
      }
      return current;
    });
  }, [environment, isAirBlue]);

  if (!open) {
    return null;
  }

  const visibleFields = (adapter?.credentialFields ?? []).filter((field) => isFieldVisible(field, credentials));
  const airBlueFields = isAirBlue
    ? visibleFields.filter((field) => AIRBLUE_MINIMAL_FIELD_KEYS.includes(field.key as (typeof AIRBLUE_MINIMAL_FIELD_KEYS)[number]))
    : visibleFields;

  async function handleSave() {
    setLocalError(null);
    if (!name.trim()) {
      setLocalError("Connection name is required.");
      panelRef.current?.querySelector<HTMLElement>('[data-testid="api-create-connection-name"]')?.focus();
      return;
    }
    const result = await onSave({
      provider,
      name: name.trim(),
      environment,
      credentials,
      ...(adapter?.baseUrlOverridable ? { base_url: createBaseUrl.trim() || null } : {}),
    });
    if (!result.ok) {
      setLocalError(result.message ?? "Could not save connection.");
    }
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-4" role="presentation">
      <button
        type="button"
        className="absolute inset-0 bg-black/40 motion-reduce:transition-none"
        aria-label="Close dialog"
        disabled={busy}
        onClick={() => {
          if (!busy) {
            onClose();
          }
        }}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        className="relative flex max-h-[100dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl border border-jp-border bg-white shadow-xl sm:max-h-[90dvh] sm:rounded-2xl"
        data-testid="api-connection-create-modal"
      >
        <header className="flex shrink-0 items-start gap-3 border-b border-jp-border px-4 py-4 sm:px-5">
          <div className="min-w-0 flex-1">
            <h2 id={titleId} className="text-lg font-semibold text-gray-900">
              Add API Connection
            </h2>
            {step === "pick" ? (
              <p className="mt-1 text-sm text-jp-muted">Choose API adapter</p>
            ) : (
              <div className="mt-2 flex items-center gap-2 text-sm">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-jp-accent/10 text-xs font-semibold text-jp-accent">
                  {(cardMeta?.icon ?? cardMeta?.label?.slice(0, 2) ?? provider.slice(0, 2)).toUpperCase()}
                </span>
                <span>{cardMeta?.label ?? adapter?.label ?? provider}</span>
                <span className="rounded-full border border-jp-border px-2 py-0.5 text-[11px] capitalize">
                  {environment === "live" ? "Live" : "Test"}
                </span>
              </div>
            )}
          </div>
          <IconButton label="Close" onClick={onClose} disabled={busy}>
            <span aria-hidden className="text-xl leading-none">
              ×
            </span>
          </IconButton>
        </header>

        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">
          {localError || error ? <p className="mb-3 text-sm text-red-600">{localError ?? error}</p> : null}

          {step === "pick" ? (
            <ProviderCatalogCards
              providers={providers}
              providerCards={providerCards}
              selectedKey={provider}
              onSelect={(key) => {
                setProvider(key);
                setCredentials({});
                setCreateBaseUrl("");
                setEnvironment("sandbox");
                setName(key === "airblue" ? airBlueSuggestedConnectionName("sandbox") : "");
                setStep("configure");
              }}
            />
          ) : (
            <div className="space-y-3" data-testid="api-connection-create-configure">
              {!installed ? (
                <p className="text-sm text-amber-700">
                  Provider adapter not installed. Engineering integration required. Credential entry is disabled.
                </p>
              ) : (
                <>
                  <label className="block text-xs">
                    Environment
                    <select
                      className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
                      value={environment}
                      onChange={(e) => setEnvironment(e.target.value)}
                      data-testid="api-create-environment"
                    >
                      {isAirBlue ? (
                        <>
                          <option value="sandbox">Test</option>
                          <option value="live">Live</option>
                        </>
                      ) : (
                        <>
                          <option value="demo">demo</option>
                          <option value="sandbox">sandbox</option>
                          <option value="live">live</option>
                        </>
                      )}
                    </select>
                  </label>
                  <label className="block text-xs">
                    Connection name
                    <input
                      className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      data-testid="api-create-connection-name"
                    />
                  </label>
                  {isAirBlue ? <AirBlueEndpointPreview environment={environment} /> : null}
                  {!isAirBlue && adapter?.baseUrlOverridable ? (
                    <label className="block text-xs">
                      Base URL
                      <input
                        className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1"
                        value={createBaseUrl}
                        onChange={(e) => setCreateBaseUrl(e.target.value)}
                        placeholder="https://api.example.com"
                      />
                    </label>
                  ) : null}
                  {!isAirBlue && !adapter?.baseUrlOverridable ? (
                    <p className="text-xs text-jp-muted">This adapter uses its built-in endpoint. A Base URL override is not supported.</p>
                  ) : null}
                  <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
                    <legend className="text-sm font-medium">Credentials</legend>
                    {airBlueFields.map((field) => (
                      <ProviderField
                        key={field.key}
                        field={field}
                        value={credentials[field.key] ?? (field.type === "select" ? field.default ?? "" : "")}
                        onChange={(value) => setCredentials((current) => ({ ...current, [field.key]: value }))}
                      />
                    ))}
                  </fieldset>
                  {!isAirBlue && (adapter?.advancedFields ?? []).length > 0 ? (
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
                </>
              )}
            </div>
          )}
        </div>

        <footer className="flex shrink-0 flex-wrap gap-2 border-t border-jp-border px-4 py-4 sm:px-5">
          {step === "configure" ? (
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 text-sm"
              disabled={busy}
              onClick={() => {
                setLocalError(null);
                setStep("pick");
              }}
            >
              Back
            </button>
          ) : null}
          <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm" disabled={busy} onClick={onClose}>
            {step === "configure" ? "Cancel" : "Close"}
          </button>
          {step === "configure" && installed && isLive ? (
            <button
              type="button"
              className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
              disabled={busy || !name.trim()}
              data-testid="api-create-save"
              onClick={() => void handleSave()}
            >
              Save securely
            </button>
          ) : null}
          {step === "configure" && installed && !isLive ? (
            <p className="self-center text-xs text-jp-muted">Live credential save is available in authenticated dashboard mode only.</p>
          ) : null}
        </footer>
      </div>
    </div>
  );
}
