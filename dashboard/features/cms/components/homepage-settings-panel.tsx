"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadPageSettings, publishPageSettings, savePageSettings } from "@/services/operational-api";

type Hero = { badge?: string; title?: string; subtitle?: string; enabled?: string | boolean };
type RouteItem = { id?: string; from?: string; to?: string; title?: string; trip_type?: string; enabled?: string | boolean };
type DestinationItem = { id?: string; code?: string; title?: string; country?: string; enabled?: string | boolean };
type DealItem = { airline?: string; from?: string; to?: string; price?: string | number; enabled?: string | boolean };
type SupportCta = {
  phone_value?: string;
  call_url?: string;
  chat_url?: string;
  cta_link?: string;
  call_enabled?: string | boolean;
  chat_enabled?: string | boolean;
};

type HomepageContent = {
  hero?: Hero;
  routes?: { title?: string; items?: RouteItem[] };
  destinations?: { title?: string; items?: DestinationItem[] };
  featured_deals?: { title?: string; items?: DealItem[] };
  support_cta?: SupportCta;
  [key: string]: unknown;
};

function asRecord(value: unknown): HomepageContent {
  return value && typeof value === "object" ? (value as HomepageContent) : {};
}

function enabledValue(value: unknown): boolean {
  return value === true || value === "1" || value === "true";
}

export function HomepageSettingsPanel() {
  const isLive = useDashboardLiveMode();
  const [content, setContent] = useState<HomepageContent>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    if (!isLive) {
      return;
    }
    void loadPageSettings("home").then((result) => {
      if (!result.ok) {
        setError(result.message ?? "Could not load homepage settings.");
        return;
      }
      const payload = ("data" in result ? result.data : result) as { content?: Record<string, unknown> };
      setContent(asRecord(payload.content));
    });
  }, [isLive]);

  if (!isLive) {
    return <p className="text-xs text-jp-muted">Homepage Page Settings are available in live dashboard mode only.</p>;
  }

  const hero = content.hero ?? {};
  const routes = content.routes?.items ?? [];
  const destinations = content.destinations?.items ?? [];
  const deals = content.featured_deals?.items ?? [];
  const support = content.support_cta ?? {};

  async function persist(next: HomepageContent, publish = false) {
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await savePageSettings("home", next);
    if (!result.ok) {
      setError(result.message ?? "Save failed");
      setBusy(false);
      return;
    }
    setContent(next);
    if (publish) {
      const published = await publishPageSettings("home");
      if (!published.ok) {
        setError(published.message ?? "Publish failed");
        setBusy(false);
        return;
      }
      setSuccess("Homepage published.");
    } else {
      setSuccess("Homepage draft saved.");
    }
    setBusy(false);
  }

  return (
    <section className="space-y-4 rounded-xl border border-jp-border bg-white p-4" data-testid="homepage-settings-panel">
      <h2 className="text-sm font-semibold">Homepage (live Page Settings)</h2>
      <p className="text-xs text-jp-muted">
        Structured controls for the published JetPakistan homepage. This is not a universal page builder.
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
        <legend className="text-sm font-medium">Hero</legend>
        <label className="block text-xs">Badge
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.badge ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, badge: e.target.value } })} />
        </label>
        <label className="block text-xs">Headline
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.title ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, title: e.target.value } })} />
        </label>
        <label className="block text-xs">Intro
          <textarea className="mt-1 w-full rounded-lg border border-jp-border p-2" rows={3} value={hero.subtitle ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, subtitle: e.target.value } })} />
        </label>
      </fieldset>

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
        <legend className="text-sm font-medium">Trending routes</legend>
        {routes.map((item, index) => (
          <div key={item.id ?? index} className="grid gap-2 sm:grid-cols-4">
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="From" value={item.from ?? ""} onChange={(e) => {
              const items = [...routes];
              items[index] = { ...item, from: e.target.value.toUpperCase() };
              setContent({ ...content, routes: { ...content.routes, items } });
            }} />
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="To" value={item.to ?? ""} onChange={(e) => {
              const items = [...routes];
              items[index] = { ...item, to: e.target.value.toUpperCase() };
              setContent({ ...content, routes: { ...content.routes, items } });
            }} />
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Title" value={item.title ?? ""} onChange={(e) => {
              const items = [...routes];
              items[index] = { ...item, title: e.target.value };
              setContent({ ...content, routes: { ...content.routes, items } });
            }} />
            <label className="flex items-center gap-2 text-xs">
              <input type="checkbox" checked={enabledValue(item.enabled)} onChange={(e) => {
                const items = [...routes];
                items[index] = { ...item, enabled: e.target.checked ? "1" : "0" };
                setContent({ ...content, routes: { ...content.routes, items } });
              }} />
              Enabled
            </label>
          </div>
        ))}
      </fieldset>

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
        <legend className="text-sm font-medium">Destinations</legend>
        {destinations.map((item, index) => (
          <div key={item.id ?? index} className="grid gap-2 sm:grid-cols-3">
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="IATA" value={item.code ?? ""} onChange={(e) => {
              const items = [...destinations];
              items[index] = { ...item, code: e.target.value.toUpperCase() };
              setContent({ ...content, destinations: { ...content.destinations, items } });
            }} />
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Title" value={item.title ?? ""} onChange={(e) => {
              const items = [...destinations];
              items[index] = { ...item, title: e.target.value };
              setContent({ ...content, destinations: { ...content.destinations, items } });
            }} />
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Country" value={item.country ?? ""} onChange={(e) => {
              const items = [...destinations];
              items[index] = { ...item, country: e.target.value };
              setContent({ ...content, destinations: { ...content.destinations, items } });
            }} />
          </div>
        ))}
      </fieldset>

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
        <legend className="text-sm font-medium">Featured deals</legend>
        {deals.map((item, index) => (
          <div key={`${item.airline}-${index}`} className="grid gap-2 sm:grid-cols-4">
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Airline" value={item.airline ?? ""} onChange={(e) => {
              const items = [...deals];
              items[index] = { ...item, airline: e.target.value };
              setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
            }} />
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="From" value={item.from ?? ""} onChange={(e) => {
              const items = [...deals];
              items[index] = { ...item, from: e.target.value.toUpperCase() };
              setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
            }} />
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="To" value={item.to ?? ""} onChange={(e) => {
              const items = [...deals];
              items[index] = { ...item, to: e.target.value.toUpperCase() };
              setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
            }} />
            <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Price" value={String(item.price ?? "")} onChange={(e) => {
              const items = [...deals];
              items[index] = { ...item, price: e.target.value };
              setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
            }} />
          </div>
        ))}
      </fieldset>

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
        <legend className="text-sm font-medium">Support CTA</legend>
        <label className="block text-xs">Phone
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={support.phone_value ?? ""} onChange={(e) => setContent({ ...content, support_cta: { ...support, phone_value: e.target.value } })} />
        </label>
        <label className="block text-xs">Call URL
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={support.call_url ?? ""} onChange={(e) => setContent({ ...content, support_cta: { ...support, call_url: e.target.value } })} />
        </label>
        <label className="block text-xs">Chat URL
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={support.chat_url ?? ""} onChange={(e) => setContent({ ...content, support_cta: { ...support, chat_url: e.target.value } })} />
        </label>
      </fieldset>

      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
          disabled={busy}
          data-testid="homepage-settings-save"
          onClick={() => void persist(content)}
        >
          {busy ? "Saving…" : "Save draft"}
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
          disabled={busy}
          onClick={() => void persist(content, true)}
        >
          Publish
        </button>
        <a
          className="inline-flex min-h-11 items-center rounded-xl border border-jp-border px-3 text-sm"
          href="/"
          target="_blank"
          rel="noreferrer"
        >
          Preview homepage
        </a>
      </div>
    </section>
  );
}
