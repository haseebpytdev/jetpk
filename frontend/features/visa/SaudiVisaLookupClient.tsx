'use client';

import { useCallback, useEffect, useMemo, useState } from "react";

type Criterion = { key: string; label: string };

type Caps = {
  country_label: string;
  service_label: string;
  criteria: Criterion[];
  nationality_required: boolean;
  official_fallback_url: string;
  document_source_type: string;
};

type LookupResult = {
  status: string;
  fields: Record<string, string | null | undefined>;
  document_ref: string | null;
  attribution: string;
  lookup_session_id: string;
};

const API = "/api/public/visa";

export function SaudiVisaLookupClient() {
  const [caps, setCaps] = useState<Caps | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [captchaSrc, setCaptchaSrc] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<LookupResult | null>(null);

  const [firstCriterion, setFirstCriterion] = useState("passport_number");
  const [secondCriterion, setSecondCriterion] = useState("visa_number");
  const [firstValue, setFirstValue] = useState("");
  const [secondValue, setSecondValue] = useState("");
  const [nationality, setNationality] = useState("PAK");
  const [captchaAnswer, setCaptchaAnswer] = useState("");

  const officialUrl = caps?.official_fallback_url ?? "https://visa.mofa.gov.sa/visaservices/searchvisa";

  const loadCaps = useCallback(async () => {
    const res = await fetch(`${API}/capabilities`, { credentials: "include" });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      setError(body.message ?? "Visa lookup is unavailable.");
      return;
    }
    setCaps(await res.json());
  }, []);

  const startSession = useCallback(async () => {
    setLoading(true);
    setError(null);
    setResult(null);
    try {
      const res = await fetch(`${API}/start`, { method: "POST", credentials: "include" });
      const body = await res.json();
      if (!res.ok) {
        setError(body.message ?? "Unable to start lookup.");
        return;
      }
      setSessionId(body.lookup_session_id);
      setCaptchaSrc(`data:${body.captcha.mime};base64,${body.captcha.image_base64}`);
      setCaptchaAnswer("");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadCaps().then(() => startSession());
  }, [loadCaps, startSession]);

  const refreshCaptcha = async () => {
    if (!sessionId) return;
    setLoading(true);
    try {
      const res = await fetch(`${API}/captcha/refresh`, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ lookup_session_id: sessionId }),
      });
      const body = await res.json();
      if (!res.ok) {
        setError(body.message ?? "Unable to refresh captcha.");
        return;
      }
      setCaptchaSrc(`data:${body.captcha.mime};base64,${body.captcha.image_base64}`);
      setCaptchaAnswer("");
    } finally {
      setLoading(false);
    }
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!sessionId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API}/lookup`, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          lookup_session_id: sessionId,
          first_criterion: firstCriterion,
          first_value: firstValue,
          second_criterion: secondCriterion,
          second_value: secondValue,
          nationality,
          captcha_answer: captchaAnswer,
        }),
      });
      const body = await res.json();
      if (!res.ok) {
        setError(body.message ?? "Lookup failed.");
        if (body.error === "CAPTCHA_INVALID" || body.error === "CAPTCHA_EXPIRED") {
          await refreshCaptcha();
        }
        return;
      }
      setResult(body);
    } finally {
      setLoading(false);
    }
  };

  const postExport = async (path: string, filename: string) => {
    if (!result?.document_ref || !result.lookup_session_id) return;
    const res = await fetch(`${API}${path}`, {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        lookup_session_id: result.lookup_session_id,
        document_ref: result.document_ref,
      }),
    });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      setError(body.message ?? "Export failed.");
      return;
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  };

  const fieldEntries = useMemo(() => {
    if (!result?.fields) return [];
    return Object.entries(result.fields).filter(([k, v]) => v && k !== "passport_number");
  }, [result]);

  return (
    <div className="space-y-jp-xl">
      <p className="text-jp-sm text-jp-muted">
        Source: Saudi Ministry of Foreign Affairs. JetPakistan does not issue visas. Generated PDF/image files are copies of the
        official MOFA result page (MOFA does not provide a native PDF download).
      </p>

      <p>
        <a className="text-jp-sm font-medium text-jp-accent underline" href={officialUrl} target="_blank" rel="noreferrer">
          Open Official MOFA Visa Service
        </a>
      </p>

      {error ? (
        <div role="alert" className="rounded-jp-lg border border-red-300 bg-red-50 p-jp-md text-jp-sm text-red-900">
          {error}
        </div>
      ) : null}

      {loading ? <p className="text-jp-sm text-jp-muted" aria-live="polite">Loading…</p> : null}

      {!result ? (
        <form onSubmit={submit} className="space-y-jp-lg rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card">
          <fieldset className="space-y-jp-md">
            <legend className="text-jp-h4 font-semibold text-jp-text">Saudi visa lookup</legend>
            <div className="grid gap-jp-md md:grid-cols-2">
              <label className="block text-jp-sm">
                <span className="mb-1 block font-medium">First criterion</span>
                <select
                  className="w-full rounded-jp-md border border-jp-border bg-white px-3 py-2"
                  value={firstCriterion}
                  onChange={(e) => setFirstCriterion(e.target.value)}
                >
                  {(caps?.criteria ?? []).map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-jp-sm">
                <span className="mb-1 block font-medium">First value</span>
                <input
                  className="w-full rounded-jp-md border border-jp-border px-3 py-2"
                  value={firstValue}
                  onChange={(e) => setFirstValue(e.target.value)}
                  autoComplete="off"
                  required
                />
              </label>
              <label className="block text-jp-sm">
                <span className="mb-1 block font-medium">Second criterion</span>
                <select
                  className="w-full rounded-jp-md border border-jp-border bg-white px-3 py-2"
                  value={secondCriterion}
                  onChange={(e) => setSecondCriterion(e.target.value)}
                >
                  {(caps?.criteria ?? []).map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-jp-sm">
                <span className="mb-1 block font-medium">Second value</span>
                <input
                  className="w-full rounded-jp-md border border-jp-border px-3 py-2"
                  value={secondValue}
                  onChange={(e) => setSecondValue(e.target.value)}
                  autoComplete="off"
                  required
                />
              </label>
              <label className="block text-jp-sm md:col-span-2">
                <span className="mb-1 block font-medium">Nationality (ISO-3)</span>
                <input
                  className="w-full rounded-jp-md border border-jp-border px-3 py-2"
                  value={nationality}
                  onChange={(e) => setNationality(e.target.value.toUpperCase())}
                  maxLength={3}
                  required
                />
              </label>
            </div>
          </fieldset>

          <fieldset className="space-y-jp-md">
            <legend className="text-jp-sm font-semibold">Image code</legend>
            {captchaSrc ? (
              <img src={captchaSrc} alt="Provider captcha challenge. Enter the characters shown." className="h-16 w-auto border border-jp-border" />
            ) : null}
            <div className="flex flex-wrap items-end gap-3">
              <label className="block min-w-[12rem] flex-1 text-jp-sm">
                <span className="mb-1 block font-medium">Enter image code</span>
                <input
                  className="w-full rounded-jp-md border border-jp-border px-3 py-2"
                  value={captchaAnswer}
                  onChange={(e) => setCaptchaAnswer(e.target.value)}
                  autoComplete="off"
                  required
                />
              </label>
              <button type="button" className="rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm" onClick={() => void refreshCaptcha()}>
                Refresh code
              </button>
            </div>
          </fieldset>

          <button type="submit" className="rounded-jp-md bg-jp-accent px-4 py-2 text-jp-sm font-semibold text-white" disabled={loading}>
            Search visa
          </button>
        </form>
      ) : (
        <section className="space-y-jp-lg rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card" aria-labelledby="visa-result-heading">
          <h2 id="visa-result-heading" className="text-jp-h3 font-semibold text-jp-text">
            Visa result
          </h2>
          <p className="text-jp-sm text-jp-muted">{result.attribution}</p>
          <dl className="grid gap-3 sm:grid-cols-2">
            {fieldEntries.map(([key, value]) => (
              <div key={key}>
                <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">{key.replaceAll("_", " ")}</dt>
                <dd className="text-jp-sm font-medium text-jp-text">{String(value)}</dd>
              </div>
            ))}
            {result.fields.passport_number_masked ? (
              <div>
                <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">passport</dt>
                <dd className="text-jp-sm font-medium text-jp-text">{result.fields.passport_number_masked}</dd>
              </div>
            ) : null}
          </dl>
          <div className="flex flex-wrap gap-3">
            <button type="button" className="rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm" onClick={() => void postExport("/document", "visa-document.html")}>
              View visa
            </button>
            <button type="button" className="rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm" onClick={() => window.print()}>
              Print
            </button>
            <button type="button" className="rounded-jp-md bg-jp-accent px-3 py-2 text-jp-sm font-semibold text-white" onClick={() => void postExport("/export/pdf", "visa-copy.pdf")}>
              Download Visa PDF
            </button>
            <button type="button" className="rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm" onClick={() => void postExport("/export/png", "visa-copy.png")}>
              Download Image
            </button>
            <button
              type="button"
              className="rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm"
              onClick={() => {
                setResult(null);
                void startSession();
              }}
            >
              New search
            </button>
          </div>
          <p className="text-jp-xs text-jp-muted">PDF and image downloads are JetPakistan-generated copies of the official MOFA result — not MOFA-issued PDF files.</p>
        </section>
      )}
    </div>
  );
}
