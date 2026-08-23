"use client";

import { useEffect, useMemo, useState } from "react";
import { CmsMediaPickerDialog } from "@/features/cms/components/cms-media-picker-dialog";
import {
  beginPageSettingsPreview,
  loadPageSettings,
  publishPageSettings,
  savePageSettings,
  uploadPageSettingsAsset,
} from "@/services/operational-api";

type Hero = {
  eyebrow?: string;
  headline?: string;
  headline_highlight?: string;
  subtitle?: string;
  enabled?: string | boolean;
  image_alt?: string;
  focal_point?: string;
  overlay_strength?: string;
  cta_text?: string;
  cta_link?: string;
};
type RouteItem = {
  id?: string;
  from?: string;
  to?: string;
  title?: string;
  trip_type?: string;
  enabled?: string | boolean;
};
type DestinationItem = {
  id?: string;
  code?: string;
  title?: string;
  country?: string;
  subtitle?: string;
  enabled?: string | boolean;
};
type DealItem = {
  id?: string;
  airline?: string;
  from?: string;
  to?: string;
  price?: string | number;
  title?: string;
  badge?: string;
  description?: string;
  enabled?: string | boolean;
};
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

type AssetMap = Record<string, { id?: number; url?: string; alt?: string }>;

function asRecord(value: unknown): HomepageContent {
  return value && typeof value === "object" ? (value as HomepageContent) : {};
}

function enabledValue(value: unknown): boolean {
  return value === true || value === "1" || value === "true" || value === undefined;
}

function newId(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}

function moveItem<T>(items: T[], index: number, direction: -1 | 1): T[] {
  const next = [...items];
  const target = index + direction;
  if (target < 0 || target >= next.length) return next;
  const tmp = next[index];
  next[index] = next[target];
  next[target] = tmp;
  return next;
}

export function HomepageSettingsPanel() {
  const [content, setContent] = useState<HomepageContent>({});
  const [assets, setAssets] = useState<AssetMap>({});
  const [formSource, setFormSource] = useState<string>("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [pickerKey, setPickerKey] = useState<string | null>(null);

  useEffect(() => {
    void loadPageSettings("home").then((result) => {
      if (!result.ok) {
        setError(result.message ?? "Could not load homepage settings.");
        return;
      }
      const payload = ("data" in result ? result.data : result) as {
        content?: Record<string, unknown>;
        assets?: Array<Record<string, unknown>>;
        editorMeta?: { form_source?: string; effective_source?: string };
      };
      setContent(asRecord(payload.content));
      const mapped: AssetMap = {};
      for (const row of payload.assets ?? []) {
        const key = String(row.asset_key ?? "");
        if (!key) continue;
        mapped[key] = {
          id: typeof row.id === "number" ? row.id : undefined,
          url: typeof row.url === "string" ? row.url : typeof row.public_url === "string" ? row.public_url : undefined,
          alt: typeof row.alt_text === "string" ? row.alt_text : undefined,
        };
      }
      setAssets(mapped);
      setFormSource(payload.editorMeta?.effective_source ?? payload.editorMeta?.form_source ?? "");
    });
  }, []);

  const hero = content.hero ?? {};
  const routes = content.routes?.items ?? [];
  const destinations = content.destinations?.items ?? [];
  const deals = content.featured_deals?.items ?? [];
  const support = content.support_cta ?? {};

  const desktopHero = assets.hero_background;
  const mobileHero = assets.hero_background_mobile;

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
      setSuccess("Homepage published. Public production content updated.");
    } else {
      setSuccess("Homepage draft saved. Public production content unchanged until Publish.");
    }
    setBusy(false);
  }

  async function previewDraft() {
    setBusy(true);
    setError(null);
    const saved = await savePageSettings("home", content);
    if (!saved.ok) {
      setError(saved.message ?? "Save failed before preview");
      setBusy(false);
      return;
    }
    const preview = await beginPageSettingsPreview("home");
    setBusy(false);
    if (!preview.ok) {
      setError(preview.message ?? "Preview session failed");
      return;
    }
    window.open("/?jp_preview=1", "_blank", "noopener,noreferrer");
    setSuccess("Draft saved. Preview opened in a new tab.");
  }

  async function uploadAsset(assetKey: string, file: File) {
    const formData = new FormData();
    formData.set("asset_key", assetKey);
    formData.set("file", file);
    formData.set("alt_text", hero.image_alt || file.name);
    const result = await uploadPageSettingsAsset("home", formData);
    if (!result.ok) {
      throw new Error(result.message ?? "Asset upload failed");
    }
    const refreshed = await loadPageSettings("home");
    if (refreshed.ok) {
      const payload = ("data" in refreshed ? refreshed.data : refreshed) as {
        assets?: Array<Record<string, unknown>>;
      };
      const mapped: AssetMap = {};
      for (const row of payload.assets ?? []) {
        const key = String(row.asset_key ?? "");
        if (!key) continue;
        mapped[key] = {
          id: typeof row.id === "number" ? row.id : undefined,
          url: typeof row.url === "string" ? row.url : typeof row.public_url === "string" ? row.public_url : undefined,
          alt: typeof row.alt_text === "string" ? row.alt_text : undefined,
        };
      }
      setAssets(mapped);
    }
  }

  const pickerTitle = useMemo(() => {
    if (pickerKey === "hero_background") return "Select desktop hero media";
    if (pickerKey === "hero_background_mobile") return "Select mobile hero media";
    return "Select media";
  }, [pickerKey]);

  return (
    <section className="space-y-4 rounded-xl border border-jp-border bg-white p-4" data-testid="cms-homepage-builder">
      <div>
        <h2 className="text-sm font-semibold">Homepage Builder</h2>
        <p className="text-xs text-jp-muted">
          Structured JetPakistan homepage sections with draft / preview / publish. Existing published content is preserved on load
          {formSource ? ` (${formSource.replaceAll("_", " ")})` : ""}.
        </p>
      </div>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3" data-testid="cms-hero-editor">
        <legend className="text-sm font-medium">Hero</legend>
        <label className="block text-xs">Eyebrow
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.eyebrow ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, eyebrow: e.target.value } })} />
        </label>
        <label className="block text-xs">Headline
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.headline ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, headline: e.target.value } })} />
        </label>
        <label className="block text-xs">Highlighted text
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.headline_highlight ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, headline_highlight: e.target.value } })} />
        </label>
        <label className="block text-xs">Description
          <textarea className="mt-1 w-full rounded-lg border border-jp-border p-2" rows={3} value={hero.subtitle ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, subtitle: e.target.value } })} />
        </label>
        <label className="block text-xs">Alt text
          <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.image_alt ?? ""} onChange={(e) => setContent({ ...content, hero: { ...hero, image_alt: e.target.value } })} />
        </label>
        <label className="block text-xs">Focal point
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.focal_point ?? "center"} onChange={(e) => setContent({ ...content, hero: { ...hero, focal_point: e.target.value } })}>
            <option value="left">Left</option>
            <option value="center">Center</option>
            <option value="right">Right</option>
          </select>
        </label>
        <label className="block text-xs">Overlay strength
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={hero.overlay_strength ?? "medium"} onChange={(e) => setContent({ ...content, hero: { ...hero, overlay_strength: e.target.value } })}>
            <option value="light">Light</option>
            <option value="medium">Medium</option>
            <option value="strong">Strong</option>
          </select>
        </label>

        <div className="grid gap-3 sm:grid-cols-2" data-testid="cms-hero-media-picker">
          {([
            ["hero_background", "Desktop hero media", desktopHero],
            ["hero_background_mobile", "Mobile hero media", mobileHero],
          ] as const).map(([key, label, asset]) => (
            <div key={key} className="rounded-lg border border-dashed border-jp-border p-3">
              <p className="text-xs font-medium">{label}</p>
              <div className="mt-2 aspect-[16/9] overflow-hidden rounded-md bg-jp-page">
                {asset?.url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={asset.url} alt={asset.alt || label} className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full items-center justify-center text-xs text-jp-muted">No image</div>
                )}
              </div>
              <div className="mt-2 flex flex-wrap gap-2">
                <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" onClick={() => setPickerKey(key)}>
                  Select from Media Library
                </button>
                <label className="inline-flex cursor-pointer rounded-lg border border-jp-border px-2 py-1 text-xs">
                  Upload
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (!file) return;
                      void uploadAsset(key, file).catch((err: Error) => setError(err.message));
                    }}
                  />
                </label>
              </div>
            </div>
          ))}
        </div>
      </fieldset>

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3" data-testid="cms-trending-routes-repeater">
        <legend className="text-sm font-medium">Trending routes</legend>
        {routes.map((item, index) => (
          <div key={item.id ?? index} className="rounded-lg border border-jp-border p-2">
            <div className="grid gap-2 sm:grid-cols-4">
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Origin" value={item.from ?? ""} onChange={(e) => {
                const items = [...routes];
                items[index] = { ...item, from: e.target.value.toUpperCase() };
                setContent({ ...content, routes: { ...content.routes, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Destination" value={item.to ?? ""} onChange={(e) => {
                const items = [...routes];
                items[index] = { ...item, to: e.target.value.toUpperCase() };
                setContent({ ...content, routes: { ...content.routes, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Display title" value={item.title ?? ""} onChange={(e) => {
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
            <div className="mt-2 flex flex-wrap gap-2">
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => setContent({ ...content, routes: { ...content.routes, items: moveItem(routes, index, -1) } })}>Move up</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => setContent({ ...content, routes: { ...content.routes, items: moveItem(routes, index, 1) } })}>Move down</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => {
                const items = [...routes];
                items.splice(index + 1, 0, { ...item, id: newId("route") });
                setContent({ ...content, routes: { ...content.routes, items } });
              }}>Duplicate</button>
              <button type="button" className="rounded border border-red-200 px-2 py-1 text-xs text-red-700" onClick={() => {
                const items = routes.filter((_, i) => i !== index);
                setContent({ ...content, routes: { ...content.routes, items } });
              }}>Delete</button>
            </div>
          </div>
        ))}
        <button
          type="button"
          className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
          onClick={() => setContent({
            ...content,
            routes: { ...content.routes, items: [...routes, { id: newId("route"), from: "", to: "", title: "", enabled: "1" }] },
          })}
        >
          + Add route
        </button>
      </fieldset>

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3" data-testid="cms-destinations-repeater">
        <legend className="text-sm font-medium">Destinations</legend>
        {destinations.map((item, index) => (
          <div key={item.id ?? index} className="rounded-lg border border-jp-border p-2">
            <div className="grid gap-2 sm:grid-cols-4">
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="IATA" value={item.code ?? ""} onChange={(e) => {
                const items = [...destinations];
                items[index] = { ...item, code: e.target.value.toUpperCase() };
                setContent({ ...content, destinations: { ...content.destinations, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="City" value={item.title ?? ""} onChange={(e) => {
                const items = [...destinations];
                items[index] = { ...item, title: e.target.value };
                setContent({ ...content, destinations: { ...content.destinations, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Country" value={item.country ?? ""} onChange={(e) => {
                const items = [...destinations];
                items[index] = { ...item, country: e.target.value };
                setContent({ ...content, destinations: { ...content.destinations, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Subtitle" value={item.subtitle ?? ""} onChange={(e) => {
                const items = [...destinations];
                items[index] = { ...item, subtitle: e.target.value };
                setContent({ ...content, destinations: { ...content.destinations, items } });
              }} />
            </div>
            <div className="mt-2 flex flex-wrap gap-2">
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => setContent({ ...content, destinations: { ...content.destinations, items: moveItem(destinations, index, -1) } })}>Move up</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => setContent({ ...content, destinations: { ...content.destinations, items: moveItem(destinations, index, 1) } })}>Move down</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => {
                const items = [...destinations];
                items.splice(index + 1, 0, { ...item, id: newId("dest") });
                setContent({ ...content, destinations: { ...content.destinations, items } });
              }}>Duplicate</button>
              <button type="button" className="rounded border border-red-200 px-2 py-1 text-xs text-red-700" onClick={() => {
                const items = destinations.filter((_, i) => i !== index);
                setContent({ ...content, destinations: { ...content.destinations, items } });
              }}>Delete</button>
            </div>
          </div>
        ))}
        <button
          type="button"
          className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
          onClick={() => setContent({
            ...content,
            destinations: { ...content.destinations, items: [...destinations, { id: newId("dest"), code: "", title: "", country: "", enabled: "1" }] },
          })}
        >
          + Add destination
        </button>
      </fieldset>

      <fieldset className="space-y-2 rounded-lg border border-jp-border p-3" data-testid="cms-featured-deals-repeater">
        <legend className="text-sm font-medium">Featured deals</legend>
        {deals.map((item, index) => (
          <div key={item.id ?? `${item.airline}-${index}`} className="rounded-lg border border-jp-border p-2">
            <div className="grid gap-2 sm:grid-cols-4">
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Airline/provider" value={item.airline ?? ""} onChange={(e) => {
                const items = [...deals];
                items[index] = { ...item, airline: e.target.value };
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Origin" value={item.from ?? ""} onChange={(e) => {
                const items = [...deals];
                items[index] = { ...item, from: e.target.value.toUpperCase() };
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Destination" value={item.to ?? ""} onChange={(e) => {
                const items = [...deals];
                items[index] = { ...item, to: e.target.value.toUpperCase() };
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Display price" value={String(item.price ?? "")} onChange={(e) => {
                const items = [...deals];
                items[index] = { ...item, price: e.target.value };
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }} />
            </div>
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Title/headline" value={item.title ?? ""} onChange={(e) => {
                const items = [...deals];
                items[index] = { ...item, title: e.target.value };
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }} />
              <input className="rounded-lg border border-jp-border px-2 py-1 text-xs" placeholder="Optional badge" value={item.badge ?? ""} onChange={(e) => {
                const items = [...deals];
                items[index] = { ...item, badge: e.target.value };
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }} />
            </div>
            <div className="mt-2 flex flex-wrap gap-2">
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => setContent({ ...content, featured_deals: { ...content.featured_deals, items: moveItem(deals, index, -1) } })}>Move up</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => setContent({ ...content, featured_deals: { ...content.featured_deals, items: moveItem(deals, index, 1) } })}>Move down</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => {
                const items = [...deals];
                items.splice(index + 1, 0, { ...item, id: newId("deal") });
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }}>Duplicate</button>
              <button type="button" className="rounded border border-red-200 px-2 py-1 text-xs text-red-700" onClick={() => {
                const items = deals.filter((_, i) => i !== index);
                setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
              }}>Delete</button>
            </div>
          </div>
        ))}
        <button
          type="button"
          className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
          onClick={() => setContent({
            ...content,
            featured_deals: {
              ...content.featured_deals,
              items: [...deals, { id: newId("deal"), airline: "", from: "", to: "", price: "", title: "", enabled: "1" }],
            },
          })}
        >
          + Add featured deal
        </button>
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
        <button type="button" className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60" disabled={busy} data-testid="homepage-settings-save" onClick={() => void persist(content)}>
          {busy ? "Saving…" : "Save draft"}
        </button>
        <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60" disabled={busy} onClick={() => void previewDraft()}>
          Preview
        </button>
        <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60" disabled={busy} onClick={() => void persist(content, true)}>
          Publish
        </button>
      </div>

      <CmsMediaPickerDialog
        open={pickerKey !== null}
        title={pickerTitle}
        onClose={() => setPickerKey(null)}
        onUploadFile={async (file) => {
          if (!pickerKey) return;
          await uploadAsset(pickerKey, file);
        }}
        onSelect={() => {
          // Library select still uses upload-to-page-asset path for canonical ClientPageAsset storage.
          // Selecting an existing library item currently requires re-upload into the page asset slot
          // until a dedicated attach-from-library endpoint is authorized.
          setPickerKey(null);
          setSuccess("Use Upload on the hero media card to attach media into the homepage asset slot (canonical ClientPageAsset). Media Library remains available for browsing.");
        }}
      />
    </section>
  );
}
