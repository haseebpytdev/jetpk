"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { CmsMediaPickerDialog } from "@/features/cms/components/cms-media-picker-dialog";
import {
  attachPageSettingsAsset,
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
  image_asset_key?: string;
  image_alt?: string;
  cta_url?: string;
};

type DestinationItem = {
  id?: string;
  code?: string;
  title?: string;
  country?: string;
  subtitle?: string;
  enabled?: string | boolean;
  image_asset_key?: string;
  image_alt?: string;
  link?: string;
  cta_url?: string;
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
  image_asset_key?: string;
  image_alt?: string;
};

type SectionMeta = {
  enabled?: string | boolean;
  eyebrow?: string;
  title?: string;
  subtitle?: string;
  cta_text?: string;
  cta_url?: string;
};

type TrustChip = { label?: string };
type WhyBookCard = { num?: string; title?: string; icon?: string; text?: string; enabled?: string | boolean };
type FeatureBoardItem = { value?: string; label?: string };

type SupportCta = SectionMeta & {
  phone_value?: string;
  call_url?: string;
  chat_url?: string;
  cta_link?: string;
  call_label?: string;
  chat_label?: string;
  call_enabled?: string | boolean;
  chat_enabled?: string | boolean;
};

type HomepageContent = {
  hero?: Hero;
  trust_chips?: TrustChip[];
  routes?: SectionMeta & { items?: RouteItem[] };
  destinations?: SectionMeta & { items?: DestinationItem[] };
  featured_deals?: SectionMeta & { items?: DealItem[] };
  why_book?: SectionMeta & { cards?: WhyBookCard[] };
  feature_board?: SectionMeta & { items?: FeatureBoardItem[] };
  support_cta?: SupportCta;
  [key: string]: unknown;
};

type AssetMap = Record<string, { id?: number; url?: string; alt?: string }>;

type SectionId =
  | "hero"
  | "trust_chips"
  | "routes"
  | "destinations"
  | "featured_deals"
  | "why_book"
  | "feature_board"
  | "support_cta";

type PreviewDevice = "desktop" | "tablet" | "mobile";
type MobileWorkspaceTab = "editor" | "preview";

type PickerContext =
  | { kind: "hero"; assetKey: "hero_background" | "hero_background_mobile" }
  | { kind: "route"; index: number; assetKey: string }
  | { kind: "destination"; index: number; assetKey: string }
  | { kind: "deal"; index: number; assetKey: string };

const SECTIONS: Array<{ id: SectionId; label: string; hash: string }> = [
  { id: "hero", label: "Hero", hash: "jp-section-hero" },
  { id: "trust_chips", label: "Trust chips", hash: "jp-section-trust-chips" },
  { id: "routes", label: "Trending Routes", hash: "jp-section-routes" },
  { id: "destinations", label: "Destinations", hash: "jp-section-destinations" },
  { id: "featured_deals", label: "Featured Deals", hash: "jp-section-featured-deals" },
  { id: "why_book", label: "Why Book", hash: "jp-section-why-book" },
  { id: "feature_board", label: "Feature Board", hash: "jp-section-feature-board" },
  { id: "support_cta", label: "Support CTA", hash: "jp-section-support-cta" },
];

const PREVIEW_WIDTHS: Record<PreviewDevice, number> = {
  desktop: 1280,
  tablet: 768,
  mobile: 390,
};

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

/** Mirrors JetpkHomepageAssetService / Str::slug($id, '_') for destination/deal keys. */
function slugifyAssetId(raw: string): string {
  const slug = raw
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");
  return slug !== "" ? slug : "item";
}

function routeAssetKey(itemId: string): string {
  return `route_${itemId}`;
}

function destinationAssetKey(itemId: string): string {
  return `destination_${slugifyAssetId(itemId)}`;
}

function featuredDealAssetKey(itemId: string): string {
  return `featured_deal_${slugifyAssetId(itemId)}`;
}

function ensureRouteId(item: RouteItem): RouteItem {
  return item.id ? item : { ...item, id: newId("route") };
}

function ensureDestinationId(item: DestinationItem): DestinationItem {
  return item.id ? item : { ...item, id: newId("dest") };
}

function ensureDealId(item: DealItem): DealItem {
  return item.id ? item : { ...item, id: newId("deal") };
}

function mapAssets(rows: Array<Record<string, unknown>> | undefined): AssetMap {
  const mapped: AssetMap = {};
  for (const row of rows ?? []) {
    const key = String(row.asset_key ?? "");
    if (!key) continue;
    mapped[key] = {
      id: typeof row.id === "number" ? row.id : undefined,
      url: typeof row.url === "string" ? row.url : typeof row.public_url === "string" ? row.public_url : undefined,
      alt: typeof row.alt_text === "string" ? row.alt_text : undefined,
    };
  }
  return mapped;
}

function MediaSourceIndicator({ hasCms }: { hasCms: boolean }) {
  return (
    <p className="text-[11px] text-jp-muted" data-testid="cms-media-source-indicator">
      MEDIA SOURCE: {hasCms ? "CMS" : "Fallback (none)"}
    </p>
  );
}

function Field({
  label,
  value,
  onChange,
  multiline,
  placeholder,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  multiline?: boolean;
  placeholder?: string;
}) {
  return (
    <label className="block text-xs">
      {label}
      {multiline ? (
        <textarea
          className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5 text-sm"
          rows={3}
          value={value}
          placeholder={placeholder}
          onChange={(e) => onChange(e.target.value)}
        />
      ) : (
        <input
          className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5 text-sm"
          value={value}
          placeholder={placeholder}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
    </label>
  );
}

function EnabledToggle({ checked, onChange, id }: { checked: boolean; onChange: (v: boolean) => void; id?: string }) {
  return (
    <label className="flex items-center gap-2 text-xs" htmlFor={id}>
      <input id={id} type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
      Enabled
    </label>
  );
}

function SectionHeaderFields({
  meta,
  onChange,
  showCta = true,
}: {
  meta: SectionMeta;
  onChange: (patch: SectionMeta) => void;
  showCta?: boolean;
}) {
  return (
    <div className="grid gap-2 sm:grid-cols-2">
      <EnabledToggle checked={enabledValue(meta.enabled)} onChange={(v) => onChange({ ...meta, enabled: v ? "1" : "0" })} />
      <Field label="Eyebrow" value={meta.eyebrow ?? ""} onChange={(v) => onChange({ ...meta, eyebrow: v })} />
      <Field label="Title" value={meta.title ?? ""} onChange={(v) => onChange({ ...meta, title: v })} />
      <Field label="Subtitle" value={meta.subtitle ?? ""} multiline onChange={(v) => onChange({ ...meta, subtitle: v })} />
      {showCta ? (
        <>
          <Field label="CTA label" value={meta.cta_text ?? ""} onChange={(v) => onChange({ ...meta, cta_text: v })} />
          <Field label="CTA target" value={meta.cta_url ?? ""} onChange={(v) => onChange({ ...meta, cta_url: v })} />
        </>
      ) : null}
    </div>
  );
}

function CardMediaControls({
  testId,
  label,
  assetUrl,
  assetAlt,
  hasCms,
  onSelectLibrary,
  onUpload,
  onRemove,
}: {
  testId: string;
  label: string;
  assetUrl?: string;
  assetAlt?: string;
  hasCms: boolean;
  onSelectLibrary: () => void;
  onUpload: (file: File) => void;
  onRemove: () => void;
}) {
  return (
    <div className="mt-2 rounded-lg border border-dashed border-jp-border p-3" data-testid={testId}>
      <p className="text-xs font-medium">{label}</p>
      <div className="mt-2 aspect-[16/10] overflow-hidden rounded-md bg-jp-page">
        {assetUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={assetUrl} alt={assetAlt || label} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full items-center justify-center text-xs text-jp-muted">No image</div>
        )}
      </div>
      <div className="mt-2">
        <MediaSourceIndicator hasCms={hasCms} />
      </div>
      <div className="mt-2 flex flex-wrap gap-2">
        <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" onClick={onSelectLibrary}>
          Select from Media Library
        </button>
        <label className="inline-flex cursor-pointer rounded-lg border border-jp-border px-2 py-1 text-xs">
          {hasCms ? "Replace" : "Upload"}
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (!file) return;
              onUpload(file);
              e.target.value = "";
            }}
          />
        </label>
        <button type="button" className="rounded-lg border border-red-200 px-2 py-1 text-xs text-red-700" onClick={onRemove}>
          Remove
        </button>
      </div>
      <p className="mt-1 text-[10px] text-jp-muted">
        Remove clears the card image key in draft content. No page-settings asset destroy API is available; the stored asset remains until replaced.
      </p>
    </div>
  );
}

export function HomepageSettingsPanel() {
  const [content, setContent] = useState<HomepageContent>({});
  const [assets, setAssets] = useState<AssetMap>({});
  const [formSource, setFormSource] = useState<string>("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [picker, setPicker] = useState<PickerContext | null>(null);
  const [activeSection, setActiveSection] = useState<SectionId>("hero");
  const [mobileTab, setMobileTab] = useState<MobileWorkspaceTab>("editor");
  const [previewDevice, setPreviewDevice] = useState<PreviewDevice>("desktop");
  const [previewReady, setPreviewReady] = useState(false);
  const [previewNonce, setPreviewNonce] = useState(0);
  const iframeRef = useRef<HTMLIFrameElement | null>(null);

  const activeHash = useMemo(
    () => SECTIONS.find((s) => s.id === activeSection)?.hash ?? "jp-section-hero",
    [activeSection],
  );

  /** Same-origin public homepage; nonce remounts iframe via key after preview/refresh. */
  const iframeSrc = useMemo(() => {
    if (previewReady) return `/?jp_preview=1#${activeHash}`;
    return `/#${activeHash}`;
  }, [previewReady, activeHash]);

  async function reloadHome() {
    const result = await loadPageSettings("home");
    if (!result.ok) {
      setError(result.message ?? "Could not load homepage settings.");
      return;
    }
    const payload = ("data" in result ? result.data : result) as {
      content?: Record<string, unknown>;
      assets?: Array<Record<string, unknown>>;
      editorMeta?: { form_source?: string; effective_source?: string };
    };
    const next = asRecord(payload.content);
    if (Array.isArray(next.routes?.items)) {
      next.routes = { ...next.routes, items: next.routes.items.map(ensureRouteId) };
    }
    if (Array.isArray(next.destinations?.items)) {
      next.destinations = { ...next.destinations, items: next.destinations.items.map(ensureDestinationId) };
    }
    if (Array.isArray(next.featured_deals?.items)) {
      next.featured_deals = { ...next.featured_deals, items: next.featured_deals.items.map(ensureDealId) };
    }
    setContent(next);
    setAssets(mapAssets(payload.assets));
    setFormSource(payload.editorMeta?.effective_source ?? payload.editorMeta?.form_source ?? "");
  }

  useEffect(() => {
    void reloadHome();
  }, []);

  const hero = content.hero ?? {};
  const routes = content.routes?.items ?? [];
  const destinations = content.destinations?.items ?? [];
  const deals = content.featured_deals?.items ?? [];
  const trustChips = Array.isArray(content.trust_chips) ? content.trust_chips : [];
  const whyBook = content.why_book ?? {};
  const whyCards = whyBook.cards ?? [];
  const featureBoard = content.feature_board ?? {};
  const featureItems = featureBoard.items ?? [];
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
      setPreviewNonce((n) => n + 1);
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
    setPreviewReady(true);
    setPreviewNonce((n) => n + 1);
    setSuccess("Draft saved. Live preview session ready in the iframe.");
  }

  const refreshPreview = useCallback(() => {
    setPreviewNonce((n) => n + 1);
    if (iframeRef.current) {
      iframeRef.current.src = previewReady ? `/?jp_preview=1#${activeHash}` : `/#${activeHash}`;
    }
  }, [previewReady, activeHash]);

  async function uploadAsset(assetKey: string, file: File, altText?: string) {
    const formData = new FormData();
    formData.set("asset_key", assetKey);
    formData.set("file", file);
    formData.set("alt_text", altText || hero.image_alt || file.name);
    const result = await uploadPageSettingsAsset("home", formData);
    if (!result.ok) {
      throw new Error(result.message ?? "Asset upload failed");
    }
    const refreshed = await loadPageSettings("home");
    if (refreshed.ok) {
      const payload = ("data" in refreshed ? refreshed.data : refreshed) as {
        assets?: Array<Record<string, unknown>>;
      };
      setAssets(mapAssets(payload.assets));
    }
  }

  async function attachAsset(assetKey: string, agencyMediaId: number | string, altText?: string) {
    const result = await attachPageSettingsAsset("home", {
      asset_key: assetKey,
      agency_media_id: agencyMediaId,
      alt_text: altText,
    });
    if (!result.ok) {
      throw new Error(result.message ?? "Could not attach media from library.");
    }
    await reloadHome();
  }

  function selectSection(id: SectionId) {
    setActiveSection(id);
    setPreviewNonce((n) => n + 1);
  }

  const pickerTitle = useMemo(() => {
    if (!picker) return "Select media";
    if (picker.kind === "hero") {
      return picker.assetKey === "hero_background" ? "Select desktop hero media" : "Select mobile hero media";
    }
    if (picker.kind === "route") return "Select route card media";
    if (picker.kind === "destination") return "Select destination card media";
    return "Select featured deal media";
  }, [picker]);

  function patchRoutes(items: RouteItem[]) {
    setContent({ ...content, routes: { ...content.routes, items } });
  }

  function patchDestinations(items: DestinationItem[]) {
    setContent({ ...content, destinations: { ...content.destinations, items } });
  }

  function patchDeals(items: DealItem[]) {
    setContent({ ...content, featured_deals: { ...content.featured_deals, items } });
  }

  const stickyActions = (
    <div className="sticky bottom-0 z-10 -mx-1 flex flex-wrap gap-2 border-t border-jp-border bg-white/95 px-1 py-3 backdrop-blur">
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
        onClick={() => void previewDraft()}
      >
        Preview
      </button>
      <button
        type="button"
        className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
        disabled={busy}
        onClick={() => void persist(content, true)}
      >
        Publish
      </button>
    </div>
  );

  const previewPanel = (
    <div className="flex h-full min-h-[420px] flex-col rounded-xl border border-jp-border bg-jp-page" data-testid="cms-homepage-split-preview">
      <div className="flex flex-wrap items-center gap-2 border-b border-jp-border bg-white px-3 py-2">
        {(
          [
            ["desktop", "Desktop"],
            ["tablet", "Tablet"],
            ["mobile", "Mobile"],
          ] as const
        ).map(([id, label]) => (
          <button
            key={id}
            type="button"
            className={`rounded-lg px-2.5 py-1 text-xs ${
              previewDevice === id ? "bg-jp-accent text-white" : "border border-jp-border text-jp-ink"
            }`}
            onClick={() => setPreviewDevice(id)}
          >
            {label}
          </button>
        ))}
        <button type="button" className="ml-auto rounded-lg border border-jp-border px-2.5 py-1 text-xs" onClick={refreshPreview}>
          Refresh
        </button>
      </div>
      <div className="flex flex-1 justify-center overflow-auto p-3">
        <div
          className="h-[min(70vh,720px)] overflow-hidden rounded-lg border border-jp-border bg-white shadow-sm transition-[width] duration-200"
          style={{ width: `min(100%, ${PREVIEW_WIDTHS[previewDevice]}px)` }}
        >
          <iframe
            key={`${iframeSrc}-${previewNonce}`}
            ref={iframeRef}
            title="Homepage live preview"
            src={iframeSrc}
            className="h-full w-full border-0 bg-white"
          />
        </div>
      </div>
      <p className="border-t border-jp-border px-3 py-2 text-[11px] text-jp-muted">
        Preview URL: {previewReady ? `/?jp_preview=1#${activeHash}` : `/#${activeHash}`}
        {!previewReady ? " — click Preview to start a draft session." : null}
      </p>
    </div>
  );

  const editorBody = (
    <div className="space-y-4">
      <nav className="flex flex-wrap gap-1.5" aria-label="Homepage sections" data-testid="cms-section-nav">
        {SECTIONS.map((section) => (
          <button
            key={section.id}
            type="button"
            className={`rounded-lg px-2.5 py-1.5 text-xs ${
              activeSection === section.id ? "bg-jp-accent text-white" : "border border-jp-border text-jp-ink"
            }`}
            onClick={() => selectSection(section.id)}
          >
            {section.label}
          </button>
        ))}
      </nav>

      {activeSection === "hero" ? (
        <fieldset className="space-y-2 rounded-lg border border-jp-border p-3" data-testid="cms-hero-editor">
          <legend className="text-sm font-medium">Hero</legend>
          <EnabledToggle
            checked={enabledValue(hero.enabled)}
            onChange={(v) => setContent({ ...content, hero: { ...hero, enabled: v ? "1" : "0" } })}
          />
          <div className="grid gap-2 sm:grid-cols-2">
            <Field label="Eyebrow" value={hero.eyebrow ?? ""} onChange={(v) => setContent({ ...content, hero: { ...hero, eyebrow: v } })} />
            <Field label="Headline" value={hero.headline ?? ""} onChange={(v) => setContent({ ...content, hero: { ...hero, headline: v } })} />
            <Field
              label="Highlighted text"
              value={hero.headline_highlight ?? ""}
              onChange={(v) => setContent({ ...content, hero: { ...hero, headline_highlight: v } })}
            />
            <Field
              label="Description"
              value={hero.subtitle ?? ""}
              multiline
              onChange={(v) => setContent({ ...content, hero: { ...hero, subtitle: v } })}
            />
            <Field label="CTA label" value={hero.cta_text ?? ""} onChange={(v) => setContent({ ...content, hero: { ...hero, cta_text: v } })} />
            <Field label="CTA target" value={hero.cta_link ?? ""} onChange={(v) => setContent({ ...content, hero: { ...hero, cta_link: v } })} />
            <Field label="Alt text" value={hero.image_alt ?? ""} onChange={(v) => setContent({ ...content, hero: { ...hero, image_alt: v } })} />
            <label className="block text-xs">
              Focal point
              <select
                className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5 text-sm"
                value={hero.focal_point ?? "center"}
                onChange={(e) => setContent({ ...content, hero: { ...hero, focal_point: e.target.value } })}
              >
                <option value="left">Left</option>
                <option value="center">Center</option>
                <option value="right">Right</option>
              </select>
            </label>
            <label className="block text-xs">
              Overlay strength
              <select
                className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5 text-sm"
                value={hero.overlay_strength ?? "medium"}
                onChange={(e) => setContent({ ...content, hero: { ...hero, overlay_strength: e.target.value } })}
              >
                <option value="light">Light</option>
                <option value="medium">Medium</option>
                <option value="strong">Strong</option>
              </select>
            </label>
          </div>

          <div className="grid gap-3 sm:grid-cols-2" data-testid="cms-hero-media-picker">
            {(
              [
                ["hero_background", "Desktop hero media", desktopHero],
                ["hero_background_mobile", "Mobile hero media", mobileHero],
              ] as const
            ).map(([key, label, asset]) => (
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
                <div className="mt-2">
                  <MediaSourceIndicator hasCms={Boolean(asset?.url)} />
                </div>
                <div className="mt-2 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                    onClick={() => setPicker({ kind: "hero", assetKey: key })}
                  >
                    Select from Media Library
                  </button>
                  <label className="inline-flex cursor-pointer rounded-lg border border-jp-border px-2 py-1 text-xs">
                    {asset?.url ? "Replace" : "Upload"}
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
      ) : null}

      {activeSection === "trust_chips" ? (
        <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
          <legend className="text-sm font-medium">Trust chips</legend>
          <p className="text-xs text-jp-muted">Leave a label empty to hide that chip publicly.</p>
          {(trustChips.length > 0 ? trustChips : [{ label: "" }, { label: "" }, { label: "" }, { label: "" }]).map(
            (chip, index) => (
              <Field
                key={index}
                label={`Chip ${index + 1} label`}
                value={chip.label ?? ""}
                onChange={(v) => {
                  const items = [...(trustChips.length > 0 ? trustChips : [{}, {}, {}, {}])];
                  items[index] = { ...items[index], label: v };
                  setContent({ ...content, trust_chips: items });
                }}
              />
            ),
          )}
        </fieldset>
      ) : null}

      {activeSection === "routes" ? (
        <fieldset className="space-y-3 rounded-lg border border-jp-border p-3" data-testid="cms-trending-routes-repeater">
          <legend className="text-sm font-medium">Trending Routes</legend>
          <SectionHeaderFields
            meta={content.routes ?? {}}
            onChange={(meta) => setContent({ ...content, routes: { ...content.routes, ...meta, items: routes } })}
          />
          {routes.map((raw, index) => {
            const item = ensureRouteId(raw);
            const assetKey = item.image_asset_key || routeAssetKey(item.id!);
            const asset = assets[assetKey];
            const hasCms = Boolean(asset?.url || item.image_asset_key);
            return (
              <div key={item.id ?? index} className="rounded-lg border border-jp-border p-3">
                <div className="grid gap-2 sm:grid-cols-2">
                  <Field
                    label="Origin"
                    value={item.from ?? ""}
                    onChange={(v) => {
                      const items = [...routes];
                      items[index] = { ...item, from: v.toUpperCase() };
                      patchRoutes(items);
                    }}
                  />
                  <Field
                    label="Destination"
                    value={item.to ?? ""}
                    onChange={(v) => {
                      const items = [...routes];
                      items[index] = { ...item, to: v.toUpperCase() };
                      patchRoutes(items);
                    }}
                  />
                  <Field
                    label="Display title"
                    value={item.title ?? ""}
                    onChange={(v) => {
                      const items = [...routes];
                      items[index] = { ...item, title: v };
                      patchRoutes(items);
                    }}
                  />
                  <Field
                    label="CTA / search URL"
                    value={item.cta_url ?? ""}
                    onChange={(v) => {
                      const items = [...routes];
                      items[index] = { ...item, cta_url: v };
                      patchRoutes(items);
                    }}
                  />
                  <Field
                    label="Image alt"
                    value={item.image_alt ?? ""}
                    onChange={(v) => {
                      const items = [...routes];
                      items[index] = { ...item, image_alt: v };
                      patchRoutes(items);
                    }}
                  />
                  <EnabledToggle
                    checked={enabledValue(item.enabled)}
                    onChange={(v) => {
                      const items = [...routes];
                      items[index] = { ...item, enabled: v ? "1" : "0" };
                      patchRoutes(items);
                    }}
                  />
                </div>
                <CardMediaControls
                  testId="cms-route-card-media"
                  label={`Route image (${assetKey})`}
                  assetUrl={asset?.url}
                  assetAlt={item.image_alt || asset?.alt}
                  hasCms={hasCms}
                  onSelectLibrary={() => setPicker({ kind: "route", index, assetKey })}
                  onUpload={(file) => {
                    void (async () => {
                      try {
                        await uploadAsset(assetKey, file, item.image_alt);
                        const items = [...routes];
                        items[index] = { ...item, id: item.id, image_asset_key: assetKey };
                        patchRoutes(items);
                        setSuccess("Route image uploaded.");
                      } catch (err) {
                        setError(err instanceof Error ? err.message : "Upload failed");
                      }
                    })();
                  }}
                  onRemove={() => {
                    const items = [...routes];
                    items[index] = { ...item, image_asset_key: "" };
                    patchRoutes(items);
                    setSuccess("Route image key cleared from draft. Stored asset remains until replaced.");
                  }}
                />
                <div className="mt-2 flex flex-wrap gap-2">
                  <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => patchRoutes(moveItem(routes, index, -1))}>
                    Move up
                  </button>
                  <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => patchRoutes(moveItem(routes, index, 1))}>
                    Move down
                  </button>
                  <button
                    type="button"
                    className="rounded border border-jp-border px-2 py-1 text-xs"
                    onClick={() => {
                      const items = [...routes];
                      items.splice(index + 1, 0, { ...item, id: newId("route"), image_asset_key: undefined });
                      patchRoutes(items);
                    }}
                  >
                    Duplicate
                  </button>
                  <button
                    type="button"
                    className="rounded border border-red-200 px-2 py-1 text-xs text-red-700"
                    onClick={() => patchRoutes(routes.filter((_, i) => i !== index))}
                  >
                    Delete
                  </button>
                </div>
              </div>
            );
          })}
          <button
            type="button"
            className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
            onClick={() =>
              patchRoutes([...routes, { id: newId("route"), from: "", to: "", title: "", enabled: "1" }])
            }
          >
            + Add route
          </button>
        </fieldset>
      ) : null}

      {activeSection === "destinations" ? (
        <fieldset className="space-y-3 rounded-lg border border-jp-border p-3" data-testid="cms-destinations-repeater">
          <legend className="text-sm font-medium">Destinations</legend>
          <SectionHeaderFields
            meta={content.destinations ?? {}}
            onChange={(meta) => setContent({ ...content, destinations: { ...content.destinations, ...meta, items: destinations } })}
          />
          {destinations.map((raw, index) => {
            const item = ensureDestinationId(raw);
            const assetKey = item.image_asset_key || destinationAssetKey(item.id!);
            const asset = assets[assetKey];
            const hasCms = Boolean(asset?.url || item.image_asset_key);
            return (
              <div key={item.id ?? index} className="rounded-lg border border-jp-border p-3">
                <div className="grid gap-2 sm:grid-cols-2">
                  <Field
                    label="IATA"
                    value={item.code ?? ""}
                    onChange={(v) => {
                      const items = [...destinations];
                      items[index] = { ...item, code: v.toUpperCase() };
                      patchDestinations(items);
                    }}
                  />
                  <Field
                    label="City / title"
                    value={item.title ?? ""}
                    onChange={(v) => {
                      const items = [...destinations];
                      items[index] = { ...item, title: v };
                      patchDestinations(items);
                    }}
                  />
                  <Field
                    label="Country"
                    value={item.country ?? ""}
                    onChange={(v) => {
                      const items = [...destinations];
                      items[index] = { ...item, country: v };
                      patchDestinations(items);
                    }}
                  />
                  <Field
                    label="Subtitle"
                    value={item.subtitle ?? ""}
                    onChange={(v) => {
                      const items = [...destinations];
                      items[index] = { ...item, subtitle: v };
                      patchDestinations(items);
                    }}
                  />
                  <Field
                    label="CTA / link"
                    value={item.link ?? item.cta_url ?? ""}
                    onChange={(v) => {
                      const items = [...destinations];
                      items[index] = { ...item, link: v, cta_url: v };
                      patchDestinations(items);
                    }}
                  />
                  <Field
                    label="Image alt"
                    value={item.image_alt ?? ""}
                    onChange={(v) => {
                      const items = [...destinations];
                      items[index] = { ...item, image_alt: v };
                      patchDestinations(items);
                    }}
                  />
                  <EnabledToggle
                    checked={enabledValue(item.enabled)}
                    onChange={(v) => {
                      const items = [...destinations];
                      items[index] = { ...item, enabled: v ? "1" : "0" };
                      patchDestinations(items);
                    }}
                  />
                </div>
                <CardMediaControls
                  testId="cms-destination-card-media"
                  label={`Destination image (${assetKey})`}
                  assetUrl={asset?.url}
                  assetAlt={item.image_alt || asset?.alt}
                  hasCms={hasCms}
                  onSelectLibrary={() => setPicker({ kind: "destination", index, assetKey })}
                  onUpload={(file) => {
                    void (async () => {
                      try {
                        await uploadAsset(assetKey, file, item.image_alt);
                        const items = [...destinations];
                        items[index] = { ...item, id: item.id, image_asset_key: assetKey };
                        patchDestinations(items);
                        setSuccess("Destination image uploaded.");
                      } catch (err) {
                        setError(err instanceof Error ? err.message : "Upload failed");
                      }
                    })();
                  }}
                  onRemove={() => {
                    const items = [...destinations];
                    items[index] = { ...item, image_asset_key: "" };
                    patchDestinations(items);
                    setSuccess("Destination image key cleared from draft. Stored asset remains until replaced.");
                  }}
                />
                <div className="mt-2 flex flex-wrap gap-2">
                  <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => patchDestinations(moveItem(destinations, index, -1))}>
                    Move up
                  </button>
                  <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => patchDestinations(moveItem(destinations, index, 1))}>
                    Move down
                  </button>
                  <button
                    type="button"
                    className="rounded border border-jp-border px-2 py-1 text-xs"
                    onClick={() => {
                      const items = [...destinations];
                      items.splice(index + 1, 0, { ...item, id: newId("dest"), image_asset_key: undefined });
                      patchDestinations(items);
                    }}
                  >
                    Duplicate
                  </button>
                  <button
                    type="button"
                    className="rounded border border-red-200 px-2 py-1 text-xs text-red-700"
                    onClick={() => patchDestinations(destinations.filter((_, i) => i !== index))}
                  >
                    Delete
                  </button>
                </div>
              </div>
            );
          })}
          <button
            type="button"
            className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
            onClick={() =>
              patchDestinations([
                ...destinations,
                { id: newId("dest"), code: "", title: "", country: "", enabled: "1" },
              ])
            }
          >
            + Add destination
          </button>
        </fieldset>
      ) : null}

      {activeSection === "featured_deals" ? (
        <fieldset className="space-y-3 rounded-lg border border-jp-border p-3" data-testid="cms-featured-deals-repeater">
          <legend className="text-sm font-medium">Featured Deals</legend>
          <SectionHeaderFields
            meta={content.featured_deals ?? {}}
            onChange={(meta) =>
              setContent({ ...content, featured_deals: { ...content.featured_deals, ...meta, items: deals } })
            }
          />
          {deals.map((raw, index) => {
            const item = ensureDealId(raw);
            const assetKey = item.image_asset_key || featuredDealAssetKey(item.id!);
            const asset = assets[assetKey];
            const hasCms = Boolean(asset?.url || item.image_asset_key);
            return (
              <div key={item.id ?? index} className="rounded-lg border border-jp-border p-3">
                <div className="grid gap-2 sm:grid-cols-2">
                  <Field
                    label="Airline"
                    value={item.airline ?? ""}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, airline: v };
                      patchDeals(items);
                    }}
                  />
                  <Field
                    label="Origin"
                    value={item.from ?? ""}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, from: v.toUpperCase() };
                      patchDeals(items);
                    }}
                  />
                  <Field
                    label="Destination"
                    value={item.to ?? ""}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, to: v.toUpperCase() };
                      patchDeals(items);
                    }}
                  />
                  <Field
                    label="Price"
                    value={String(item.price ?? "")}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, price: v };
                      patchDeals(items);
                    }}
                  />
                  <Field
                    label="Title"
                    value={item.title ?? ""}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, title: v };
                      patchDeals(items);
                    }}
                  />
                  <Field
                    label="Badge"
                    value={item.badge ?? ""}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, badge: v };
                      patchDeals(items);
                    }}
                  />
                  <Field
                    label="Description"
                    value={item.description ?? ""}
                    multiline
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, description: v };
                      patchDeals(items);
                    }}
                  />
                  <Field
                    label="Image alt"
                    value={item.image_alt ?? ""}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, image_alt: v };
                      patchDeals(items);
                    }}
                  />
                  <EnabledToggle
                    checked={enabledValue(item.enabled)}
                    onChange={(v) => {
                      const items = [...deals];
                      items[index] = { ...item, enabled: v ? "1" : "0" };
                      patchDeals(items);
                    }}
                  />
                </div>
                <CardMediaControls
                  testId="cms-featured-deal-media"
                  label={`Deal image (${assetKey})`}
                  assetUrl={asset?.url}
                  assetAlt={item.image_alt || asset?.alt}
                  hasCms={hasCms}
                  onSelectLibrary={() => setPicker({ kind: "deal", index, assetKey })}
                  onUpload={(file) => {
                    void (async () => {
                      try {
                        await uploadAsset(assetKey, file, item.image_alt);
                        const items = [...deals];
                        items[index] = { ...item, id: item.id, image_asset_key: assetKey };
                        patchDeals(items);
                        setSuccess("Featured deal image uploaded.");
                      } catch (err) {
                        setError(err instanceof Error ? err.message : "Upload failed");
                      }
                    })();
                  }}
                  onRemove={() => {
                    const items = [...deals];
                    items[index] = { ...item, image_asset_key: "" };
                    patchDeals(items);
                    setSuccess("Featured deal image key cleared from draft. Stored asset remains until replaced.");
                  }}
                />
                <div className="mt-2 flex flex-wrap gap-2">
                  <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => patchDeals(moveItem(deals, index, -1))}>
                    Move up
                  </button>
                  <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => patchDeals(moveItem(deals, index, 1))}>
                    Move down
                  </button>
                  <button
                    type="button"
                    className="rounded border border-jp-border px-2 py-1 text-xs"
                    onClick={() => {
                      const items = [...deals];
                      items.splice(index + 1, 0, { ...item, id: newId("deal"), image_asset_key: undefined });
                      patchDeals(items);
                    }}
                  >
                    Duplicate
                  </button>
                  <button
                    type="button"
                    className="rounded border border-red-200 px-2 py-1 text-xs text-red-700"
                    onClick={() => patchDeals(deals.filter((_, i) => i !== index))}
                  >
                    Delete
                  </button>
                </div>
              </div>
            );
          })}
          <button
            type="button"
            className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
            onClick={() =>
              patchDeals([
                ...deals,
                { id: newId("deal"), airline: "", from: "", to: "", price: "", title: "", enabled: "1" },
              ])
            }
          >
            + Add featured deal
          </button>
        </fieldset>
      ) : null}

      {activeSection === "why_book" ? (
        <fieldset className="space-y-3 rounded-lg border border-jp-border p-3">
          <legend className="text-sm font-medium">Why Book</legend>
          <SectionHeaderFields
            meta={whyBook}
            showCta={false}
            onChange={(meta) => setContent({ ...content, why_book: { ...whyBook, ...meta, cards: whyCards } })}
          />
          {whyCards.map((card, index) => (
            <div key={index} className="rounded-lg border border-jp-border p-3">
              <div className="grid gap-2 sm:grid-cols-2">
                <Field
                  label="Number"
                  value={card.num ?? ""}
                  onChange={(v) => {
                    const cards = [...whyCards];
                    cards[index] = { ...card, num: v };
                    setContent({ ...content, why_book: { ...whyBook, cards } });
                  }}
                />
                <Field
                  label="Title"
                  value={card.title ?? ""}
                  onChange={(v) => {
                    const cards = [...whyCards];
                    cards[index] = { ...card, title: v };
                    setContent({ ...content, why_book: { ...whyBook, cards } });
                  }}
                />
                <Field
                  label="Icon"
                  value={card.icon ?? ""}
                  onChange={(v) => {
                    const cards = [...whyCards];
                    cards[index] = { ...card, icon: v };
                    setContent({ ...content, why_book: { ...whyBook, cards } });
                  }}
                />
                <EnabledToggle
                  checked={enabledValue(card.enabled)}
                  onChange={(v) => {
                    const cards = [...whyCards];
                    cards[index] = { ...card, enabled: v ? "1" : "0" };
                    setContent({ ...content, why_book: { ...whyBook, cards } });
                  }}
                />
                <div className="sm:col-span-2">
                  <Field
                    label="Text"
                    value={card.text ?? ""}
                    multiline
                    onChange={(v) => {
                      const cards = [...whyCards];
                      cards[index] = { ...card, text: v };
                      setContent({ ...content, why_book: { ...whyBook, cards } });
                    }}
                  />
                </div>
              </div>
            </div>
          ))}
          <button
            type="button"
            className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
            onClick={() =>
              setContent({
                ...content,
                why_book: { ...whyBook, cards: [...whyCards, { num: "", title: "", icon: "", text: "", enabled: "1" }] },
              })
            }
          >
            + Add Why Book card
          </button>
        </fieldset>
      ) : null}

      {activeSection === "feature_board" ? (
        <fieldset className="space-y-3 rounded-lg border border-jp-border p-3">
          <legend className="text-sm font-medium">Feature Board</legend>
          <SectionHeaderFields
            meta={featureBoard}
            showCta={false}
            onChange={(meta) =>
              setContent({ ...content, feature_board: { ...featureBoard, ...meta, items: featureItems } })
            }
          />
          {featureItems.map((row, index) => (
            <div key={index} className="grid gap-2 rounded-lg border border-jp-border p-3 sm:grid-cols-2">
              <Field
                label="Value"
                value={row.value ?? ""}
                onChange={(v) => {
                  const items = [...featureItems];
                  items[index] = { ...row, value: v };
                  setContent({ ...content, feature_board: { ...featureBoard, items } });
                }}
              />
              <Field
                label="Label"
                value={row.label ?? ""}
                onChange={(v) => {
                  const items = [...featureItems];
                  items[index] = { ...row, label: v };
                  setContent({ ...content, feature_board: { ...featureBoard, items } });
                }}
              />
            </div>
          ))}
          <button
            type="button"
            className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
            onClick={() =>
              setContent({
                ...content,
                feature_board: { ...featureBoard, items: [...featureItems, { value: "", label: "" }] },
              })
            }
          >
            + Add feature board item
          </button>
        </fieldset>
      ) : null}

      {activeSection === "support_cta" ? (
        <fieldset className="space-y-2 rounded-lg border border-jp-border p-3">
          <legend className="text-sm font-medium">Support CTA</legend>
          <SectionHeaderFields
            meta={support}
            onChange={(meta) => setContent({ ...content, support_cta: { ...support, ...meta } })}
          />
          <div className="grid gap-2 sm:grid-cols-2">
            <Field
              label="Phone"
              value={support.phone_value ?? ""}
              onChange={(v) => setContent({ ...content, support_cta: { ...support, phone_value: v } })}
            />
            <Field
              label="Call URL"
              value={support.call_url ?? ""}
              onChange={(v) => setContent({ ...content, support_cta: { ...support, call_url: v } })}
            />
            <Field
              label="Call label"
              value={support.call_label ?? ""}
              onChange={(v) => setContent({ ...content, support_cta: { ...support, call_label: v } })}
            />
            <Field
              label="Chat URL"
              value={support.chat_url ?? support.cta_link ?? ""}
              onChange={(v) => setContent({ ...content, support_cta: { ...support, chat_url: v, cta_link: v } })}
            />
            <Field
              label="Chat label"
              value={support.chat_label ?? ""}
              onChange={(v) => setContent({ ...content, support_cta: { ...support, chat_label: v } })}
            />
            <label className="flex items-center gap-2 text-xs">
              <input
                type="checkbox"
                checked={enabledValue(support.call_enabled)}
                onChange={(e) => setContent({ ...content, support_cta: { ...support, call_enabled: e.target.checked ? "1" : "0" } })}
              />
              Call enabled
            </label>
            <label className="flex items-center gap-2 text-xs">
              <input
                type="checkbox"
                checked={enabledValue(support.chat_enabled)}
                onChange={(e) => setContent({ ...content, support_cta: { ...support, chat_enabled: e.target.checked ? "1" : "0" } })}
              />
              Chat enabled
            </label>
          </div>
        </fieldset>
      ) : null}

      {stickyActions}
    </div>
  );

  return (
    <section className="space-y-4 rounded-xl border border-jp-border bg-white p-4" data-testid="cms-homepage-builder">
      <div>
        <h2 className="text-sm font-semibold">Homepage Builder</h2>
        <p className="text-xs text-jp-muted">
          Structured JetPakistan homepage sections with draft / preview / publish. Existing published content is preserved on
          load
          {formSource ? ` (${formSource.replaceAll("_", " ")})` : ""}.
        </p>
      </div>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      {/* Mobile workspace tabs */}
      <div className="flex gap-2 lg:hidden">
        {(
          [
            ["editor", "Editor"],
            ["preview", "Preview"],
          ] as const
        ).map(([id, label]) => (
          <button
            key={id}
            type="button"
            className={`min-h-10 flex-1 rounded-lg px-3 text-sm ${
              mobileTab === id ? "bg-jp-accent text-white" : "border border-jp-border"
            }`}
            onClick={() => setMobileTab(id)}
          >
            {label}
          </button>
        ))}
      </div>

      <div className="lg:grid lg:grid-cols-[minmax(0,55fr)_minmax(0,45fr)] lg:items-start lg:gap-4">
        <div className={`${mobileTab === "editor" ? "block" : "hidden"} lg:block`}>{editorBody}</div>
        <div className={`${mobileTab === "preview" ? "block" : "hidden"} lg:sticky lg:top-4 lg:block`}>{previewPanel}</div>
      </div>

      <CmsMediaPickerDialog
        open={picker !== null}
        title={pickerTitle}
        onClose={() => setPicker(null)}
        onUploadFile={async (file) => {
          if (!picker) return;
          await uploadAsset(picker.assetKey, file);
          if (picker.kind === "route") {
            const items = [...routes];
            const current = ensureRouteId(items[picker.index] ?? {});
            items[picker.index] = { ...current, image_asset_key: picker.assetKey };
            patchRoutes(items);
          } else if (picker.kind === "destination") {
            const items = [...destinations];
            const current = ensureDestinationId(items[picker.index] ?? {});
            items[picker.index] = { ...current, image_asset_key: picker.assetKey };
            patchDestinations(items);
          } else if (picker.kind === "deal") {
            const items = [...deals];
            const current = ensureDealId(items[picker.index] ?? {});
            items[picker.index] = { ...current, image_asset_key: picker.assetKey };
            patchDeals(items);
          }
        }}
        onSelect={(item) => {
          if (!picker) return;
          void (async () => {
            setBusy(true);
            setError(null);
            try {
              await attachAsset(picker.assetKey, item.id, item.alt_text);
              if (picker.kind === "route") {
                const items = [...(content.routes?.items ?? routes)];
                const current = ensureRouteId(items[picker.index] ?? {});
                items[picker.index] = { ...current, image_asset_key: picker.assetKey, image_alt: item.alt_text ?? current.image_alt };
                const next = { ...content, routes: { ...content.routes, items } };
                setContent(next);
                await savePageSettings("home", next);
              } else if (picker.kind === "destination") {
                const items = [...(content.destinations?.items ?? destinations)];
                const current = ensureDestinationId(items[picker.index] ?? {});
                items[picker.index] = { ...current, image_asset_key: picker.assetKey, image_alt: item.alt_text ?? current.image_alt };
                const next = { ...content, destinations: { ...content.destinations, items } };
                setContent(next);
                await savePageSettings("home", next);
              } else if (picker.kind === "deal") {
                const items = [...(content.featured_deals?.items ?? deals)];
                const current = ensureDealId(items[picker.index] ?? {});
                items[picker.index] = { ...current, image_asset_key: picker.assetKey, image_alt: item.alt_text ?? current.image_alt };
                const next = { ...content, featured_deals: { ...content.featured_deals, items } };
                setContent(next);
                await savePageSettings("home", next);
              }
              setPicker(null);
              setSuccess("Media attached from library without re-upload.");
              await reloadHome();
            } catch (err) {
              setError(err instanceof Error ? err.message : "Could not attach media from library.");
            } finally {
              setBusy(false);
            }
          })();
        }}
      />
    </section>
  );
}
