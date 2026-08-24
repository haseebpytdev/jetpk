"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  loadOrganizationProfile,
  updateBrandingAbout,
  updateBrandingFooter,
  updateBrandingTheme,
} from "@/services/operational-api";

export function BrandingSettingsWorkspace() {
  const isLive = useDashboardLiveMode();
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [colorScheme, setColorScheme] = useState("blue_travel");
  const [schemeOptions, setSchemeOptions] = useState<string[]>(["blue_travel", "green_umrah", "dark_premium", "custom"]);
  const [tagline, setTagline] = useState("");
  const [primaryColor, setPrimaryColor] = useState("");
  const [secondaryColor, setSecondaryColor] = useState("");
  const [accentColor, setAccentColor] = useState("");
  const [footerEnabled, setFooterEnabled] = useState(true);
  const [footerDescription, setFooterDescription] = useState("");
  const [aboutPlain, setAboutPlain] = useState("");
  const [aboutHtmlActive, setAboutHtmlActive] = useState(false);
  const [backgroundNote, setBackgroundNote] = useState<string | null>(null);

  useEffect(() => {
    if (!isLive) return;
    setLoading(true);
    void loadOrganizationProfile().then((result) => {
      setLoading(false);
      if (!result.ok) {
        setError(result.message ?? "Could not load branding settings.");
        return;
      }
      const payload = ("data" in result ? result.data : result) as Record<string, unknown>;
      const theme = (payload.theme as Record<string, unknown> | undefined) ?? {};
      const footer = (payload.footer as Record<string, unknown> | undefined) ?? {};
      const about = (payload.about as Record<string, unknown> | undefined) ?? {};
      const brand = (footer.brand as Record<string, unknown> | undefined) ?? {};
      const bg = (payload.background_removal as Record<string, unknown> | undefined) ?? {};
      setColorScheme(String(theme.color_scheme ?? "blue_travel"));
      setSchemeOptions(
        Array.isArray(theme.color_scheme_options) && theme.color_scheme_options.length > 0
          ? theme.color_scheme_options.map(String)
          : ["blue_travel", "green_umrah", "dark_premium", "custom"],
      );
      setTagline(String(theme.tagline ?? ""));
      setPrimaryColor(String(theme.primary_color ?? ""));
      setSecondaryColor(String(theme.secondary_color ?? ""));
      setAccentColor(String(theme.accent_color ?? ""));
      setFooterEnabled(footer.is_enabled !== false);
      setFooterDescription(String(brand.description ?? ""));
      setAboutPlain(String(about.plain ?? ""));
      setAboutHtmlActive(Boolean(about.html_active));
      setBackgroundNote(
        typeof bg.laravel_settings_url === "string"
          ? `Logo background removal remains on Laravel (${bg.laravel_settings_url}).`
          : null,
      );
    });
  }, [isLive]);

  return (
    <section className="space-y-4 rounded-xl border border-jp-border bg-white p-4" data-testid="branding-settings-workspace">
      <div>
        <h3 className="text-sm font-semibold text-gray-900">Branding theme, footer, and about</h3>
        <p className="mt-1 text-xs text-jp-muted">
          Saves through AgencyBrandingController / footer / about-us handlers. Secrets are never returned.
        </p>
      </div>
      {loading ? <p className="text-sm text-jp-muted">Loading branding…</p> : null}
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-xs font-medium text-jp-muted">
          Color scheme
          <select
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            value={colorScheme}
            disabled={!isLive}
            onChange={(e) => setColorScheme(e.target.value)}
            data-testid="branding-color-scheme"
          >
            {schemeOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          Tagline
          <input
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            value={tagline}
            disabled={!isLive}
            onChange={(e) => setTagline(e.target.value)}
            data-testid="branding-tagline"
          />
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          Primary color
          <input
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            value={primaryColor}
            disabled={!isLive}
            onChange={(e) => setPrimaryColor(e.target.value)}
          />
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          Secondary color
          <input
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            value={secondaryColor}
            disabled={!isLive}
            onChange={(e) => setSecondaryColor(e.target.value)}
          />
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          Accent color
          <input
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            value={accentColor}
            disabled={!isLive}
            onChange={(e) => setAccentColor(e.target.value)}
          />
        </label>
      </div>
      <button
        type="button"
        className="min-h-11 rounded-xl bg-jp-accent px-4 text-sm text-white disabled:opacity-60"
        disabled={!isLive || busy !== null}
        data-testid="branding-theme-save"
        onClick={async () => {
          setBusy("theme");
          setError(null);
          setSuccess(null);
          const result = await updateBrandingTheme({
            color_scheme: colorScheme,
            tagline: tagline || null,
            primary_color: primaryColor || null,
            secondary_color: secondaryColor || null,
            accent_color: accentColor || null,
          });
          setBusy(null);
          if (!result.ok) {
            setError(result.message ?? "Theme save failed");
            return;
          }
          setSuccess("Theme saved.");
          router.refresh();
        }}
      >
        {busy === "theme" ? "Saving…" : "Save theme"}
      </button>

      <div className="border-t border-jp-border pt-4 space-y-3">
        <h4 className="text-sm font-semibold">Footer</h4>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={footerEnabled} disabled={!isLive} onChange={(e) => setFooterEnabled(e.target.checked)} />
          Footer enabled
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          Footer brand description
          <textarea
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            rows={3}
            value={footerDescription}
            disabled={!isLive}
            onChange={(e) => setFooterDescription(e.target.value)}
            data-testid="branding-footer-description"
          />
        </label>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-4 text-sm disabled:opacity-60"
          disabled={!isLive || busy !== null}
          data-testid="branding-footer-save"
          onClick={async () => {
            setBusy("footer");
            setError(null);
            setSuccess(null);
            const result = await updateBrandingFooter({
              is_enabled: footerEnabled,
              brand: { description: footerDescription },
            });
            setBusy(null);
            if (!result.ok) {
              setError(result.message ?? "Footer save failed");
              return;
            }
            setSuccess("Footer saved.");
            router.refresh();
          }}
        >
          {busy === "footer" ? "Saving…" : "Save footer"}
        </button>
      </div>

      <div className="border-t border-jp-border pt-4 space-y-3">
        <h4 className="text-sm font-semibold">About page</h4>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={aboutHtmlActive}
            disabled={!isLive}
            onChange={(e) => setAboutHtmlActive(e.target.checked)}
          />
          Use HTML override mode
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          About plain content
          <textarea
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            rows={5}
            value={aboutPlain}
            disabled={!isLive}
            onChange={(e) => setAboutPlain(e.target.value)}
            data-testid="branding-about-plain"
          />
        </label>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-4 text-sm disabled:opacity-60"
          disabled={!isLive || busy !== null}
          data-testid="branding-about-save"
          onClick={async () => {
            setBusy("about");
            setError(null);
            setSuccess(null);
            const result = await updateBrandingAbout({
              plain: aboutPlain,
              html_active: aboutHtmlActive,
            });
            setBusy(null);
            if (!result.ok) {
              setError(result.message ?? "About save failed");
              return;
            }
            setSuccess("About page saved.");
            router.refresh();
          }}
        >
          {busy === "about" ? "Saving…" : "Save about"}
        </button>
      </div>

      {backgroundNote ? <p className="text-xs text-jp-muted">{backgroundNote}</p> : null}
    </section>
  );
}
