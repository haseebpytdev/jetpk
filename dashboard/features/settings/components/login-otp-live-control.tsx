"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadLoginOtpSettings, updateLoginOtpSettings } from "@/services/operational-api";

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function LoginOtpLiveControl() {
  const isLive = useDashboardLiveMode();
  const [required, setRequired] = useState(false);
  const [source, setSource] = useState("default");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    if (!isLive) return;
    void loadLoginOtpSettings().then((result) => {
      if (!result.ok) {
        setError(result.message ?? "Could not load Login OTP settings.");
        return;
      }
      const snapshot = (payloadOf(result).snapshot ?? {}) as Record<string, unknown>;
      setRequired(Boolean(snapshot.required));
      setSource(String(snapshot.source ?? "default"));
    });
  }, [isLive]);

  if (!isLive) {
    return (
      <p className="text-sm text-jp-muted">
        Login OTP live control is available in live dashboard mode only.
      </p>
    );
  }

  return (
    <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="login-otp-live-heading">
      <h3 id="login-otp-live-heading" className="text-sm font-semibold text-gray-900">
        Login OTP (runtime authority)
      </h3>
      <p className="mt-1 text-sm text-jp-muted">
        Global email OTP gate for all roles. OTP codes are never shown here.
      </p>
      <dl className="mt-3 grid gap-3 sm:grid-cols-2">
        <div className="rounded-lg border border-jp-border px-3 py-2">
          <dt className="text-xs text-jp-muted">Current state</dt>
          <dd className="mt-1 text-sm font-medium" data-testid="security-require-login-otp">
            {required ? "Enabled" : "Disabled"}
          </dd>
        </div>
        <div className="rounded-lg border border-jp-border px-3 py-2">
          <dt className="text-xs text-jp-muted">Source</dt>
          <dd className="mt-1 text-sm font-medium">{source}</dd>
        </div>
      </dl>
      <label className="mt-4 inline-flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          checked={required}
          onChange={(event) => setRequired(event.target.checked)}
          disabled={busy}
        />
        Require login OTP
      </label>
      {error ? <p className="mt-2 text-sm text-red-700">{error}</p> : null}
      {success ? <p className="mt-2 text-sm text-emerald-700">{success}</p> : null}
      <div className="mt-3">
        <Button
          type="button"
          size="sm"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            setError(null);
            setSuccess(null);
            const result = await updateLoginOtpSettings(required);
            setBusy(false);
            if (!result.ok) {
              setError(result.message ?? "Could not save Login OTP setting.");
              return;
            }
            const snapshot = (payloadOf(result).snapshot ?? {}) as Record<string, unknown>;
            setRequired(Boolean(snapshot.required));
            setSource(String(snapshot.source ?? "default"));
            setSuccess(result.message ?? "Login OTP setting saved.");
          }}
        >
          Save Login OTP
        </Button>
      </div>
    </section>
  );
}
