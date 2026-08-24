"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { createPromoCode, loadPromoCodes, togglePromoCodeStatus } from "@/services/operational-api";

type PromoRow = {
  id: string;
  code: string;
  name: string;
  type: string;
  value: number;
  status: string;
  applies_to: string;
  currency?: string | null;
};

export function PromoCodesWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<PromoRow[]>([]);
  const [types, setTypes] = useState<string[]>(["percent", "fixed"]);
  const [statuses, setStatuses] = useState<string[]>(["active", "inactive"]);
  const [appliesTo, setAppliesTo] = useState<string[]>(["all"]);
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [type, setType] = useState("percent");
  const [value, setValue] = useState("10");
  const [status, setStatus] = useState("active");
  const [applies, setApplies] = useState("all");
  const [currency, setCurrency] = useState("PKR");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  async function reload() {
    const result = await loadPromoCodes();
    if (!result.ok) {
      setError(result.message ?? "Unable to load promo codes.");
      return;
    }
    const payload = ("data" in result ? result.data : result) as {
      promo_codes?: PromoRow[];
      types?: string[];
      statuses?: string[];
      applies_to?: string[];
    };
    setRows(payload.promo_codes ?? []);
    if (payload.types?.length) setTypes(payload.types);
    if (payload.statuses?.length) setStatuses(payload.statuses);
    if (payload.applies_to?.length) {
      setAppliesTo(payload.applies_to);
      setApplies(payload.applies_to[0] ?? "all");
    }
  }

  useEffect(() => {
    if (!isLive) return;
    void reload();
  }, [isLive]);

  return (
    <div className="space-y-4" data-testid="promo-codes-workspace">
      {!isLive ? <p className="text-xs text-jp-muted">Promo code management is available in live dashboard mode only.</p> : null}
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      <section className="rounded-xl border border-jp-border bg-white p-4 space-y-3">
        <h3 className="text-sm font-semibold">Create promo code</h3>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-xs font-medium text-jp-muted">
            Code
            <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} data-testid="promo-code-input" />
          </label>
          <label className="block text-xs font-medium text-jp-muted">
            Name
            <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label className="block text-xs font-medium text-jp-muted">
            Type
            <select className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={type} onChange={(e) => setType(e.target.value)}>
              {types.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-xs font-medium text-jp-muted">
            Value
            <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={value} onChange={(e) => setValue(e.target.value)} />
          </label>
          {type === "fixed" ? (
            <label className="block text-xs font-medium text-jp-muted">
              Currency
              <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={currency} onChange={(e) => setCurrency(e.target.value.toUpperCase())} />
            </label>
          ) : null}
          <label className="block text-xs font-medium text-jp-muted">
            Applies to
            <select className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={applies} onChange={(e) => setApplies(e.target.value)}>
              {appliesTo.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-xs font-medium text-jp-muted">
            Status
            <select className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={status} onChange={(e) => setStatus(e.target.value)}>
              {statuses.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </label>
        </div>
        <button
          type="button"
          className="min-h-11 rounded-xl bg-jp-accent px-4 text-sm text-white disabled:opacity-60"
          disabled={!isLive || busy}
          data-testid="promo-code-create"
          onClick={async () => {
            setBusy(true);
            setError(null);
            setSuccess(null);
            const result = await createPromoCode({
              code,
              name: name || null,
              type,
              value: Number(value),
              currency: type === "fixed" ? currency : null,
              applies_to: applies,
              status,
            });
            setBusy(false);
            if (!result.ok) {
              setError(result.message ?? "Create failed");
              return;
            }
            setSuccess("Promo code created.");
            setCode("");
            setName("");
            await reload();
          }}
        >
          Create promo code
        </button>
      </section>

      <section className="rounded-xl border border-jp-border bg-white p-4">
        <h3 className="text-sm font-semibold">Existing promo codes</h3>
        <ul className="mt-3 space-y-2" data-testid="promo-codes-list">
          {rows.map((row) => (
            <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-jp-border px-3 py-2 text-sm">
              <div>
                <p className="font-medium">{row.code}</p>
                <p className="text-xs text-jp-muted">
                  {row.name || "—"} · {row.type} {row.value}
                  {row.currency ? ` ${row.currency}` : ""} · {row.status}
                </p>
              </div>
              <button
                type="button"
                className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
                disabled={!isLive || busy}
                data-testid={`promo-code-toggle-${row.id}`}
                onClick={async () => {
                  setBusy(true);
                  setError(null);
                  setSuccess(null);
                  const result = await togglePromoCodeStatus(row.id);
                  setBusy(false);
                  if (!result.ok) {
                    setError(result.message ?? "Toggle failed");
                    return;
                  }
                  setSuccess("Promo status updated.");
                  await reload();
                }}
              >
                Toggle status
              </button>
            </li>
          ))}
          {rows.length === 0 ? <li className="text-sm text-jp-muted">No promo codes yet.</li> : null}
        </ul>
      </section>
    </div>
  );
}
