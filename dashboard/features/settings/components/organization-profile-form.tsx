"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadOrganizationProfile, updateOrganizationBrandingMedia, updateOrganizationProfile } from "@/services/operational-api";

type OrgForm = {
  display_name: string;
  legal_name: string;
  support_email: string;
  support_phone: string;
  website_url: string;
  office_address: string;
  city: string;
  country: string;
  timezone: string;
};

const empty: OrgForm = {
  display_name: "",
  legal_name: "",
  support_email: "",
  support_phone: "",
  website_url: "",
  office_address: "",
  city: "",
  country: "",
  timezone: "Asia/Karachi",
};

export function OrganizationProfileForm() {
  const isLive = useDashboardLiveMode();
  const router = useRouter();
  const [form, setForm] = useState<OrgForm>(empty);
  const [logoUrl, setLogoUrl] = useState<string | null>(null);
  const [faviconUrl, setFaviconUrl] = useState<string | null>(null);
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [faviconFile, setFaviconFile] = useState<File | null>(null);
  const [mediaError, setMediaError] = useState<string | null>(null);
  const [mediaBusy, setMediaBusy] = useState(false);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    if (!isLive) {
      return;
    }
    setLoading(true);
    void loadOrganizationProfile().then((result) => {
      setLoading(false);
      if (!result.ok) {
        setError(result.message ?? "Could not load organization profile.");
        return;
      }
      const payload = ("data" in result ? result.data : result) as { organization?: Record<string, unknown> };
      const org = payload.organization ?? {};
      setForm({
        display_name: String(org.display_name ?? ""),
        legal_name: String(org.legal_name ?? ""),
        support_email: String(org.support_email ?? ""),
        support_phone: String(org.support_phone ?? ""),
        website_url: String(org.website_url ?? ""),
        office_address: String(org.office_address ?? ""),
        city: String(org.city ?? ""),
        country: String(org.country ?? ""),
        timezone: String(org.timezone ?? "Asia/Karachi"),
      });
      setLogoUrl(typeof org.logo_url === "string" ? org.logo_url : null);
      setFaviconUrl(typeof org.favicon_url === "string" ? org.favicon_url : null);
    });
  }, [isLive]);

  function field(key: keyof OrgForm, label: string) {
    return (
      <label className="block text-xs font-medium text-jp-muted">
        {label}
        <input
          className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
          value={form[key]}
          onChange={(e) => setForm((prev) => ({ ...prev, [key]: e.target.value }))}
          disabled={!isLive}
        />
      </label>
    );
  }

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="organization-profile-form">
      <h3 className="text-sm font-semibold text-gray-900">Company Profile & Branding</h3>
      <p className="text-xs text-jp-muted">
        Company identity is separate from the signed-in Admin profile. Logo and favicon use the existing branding media store.
      </p>
      {loading ? <p className="text-sm text-jp-muted">Loading organization…</p> : null}
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      {logoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={logoUrl} alt="Organization logo" className="h-12 w-auto" />
      ) : (
        <p className="text-xs text-jp-muted">No company logo on file.</p>
      )}
      {faviconUrl ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={faviconUrl} alt="Favicon" className="h-8 w-8" />
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-xs font-medium text-jp-muted">
          Replace company logo
          <input
            type="file"
            accept="image/png,image/jpeg,image/svg+xml,image/webp"
            className="mt-1 block w-full text-sm"
            disabled={!isLive || mediaBusy}
            onChange={(e) => setLogoFile(e.target.files?.[0] ?? null)}
            data-testid="company-logo-upload"
          />
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          Replace favicon
          <input
            type="file"
            accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico"
            className="mt-1 block w-full text-sm"
            disabled={!isLive || mediaBusy}
            onChange={(e) => setFaviconFile(e.target.files?.[0] ?? null)}
            data-testid="company-favicon-upload"
          />
        </label>
      </div>
      {mediaError ? <p className="text-sm text-red-600">{mediaError}</p> : null}
      <button
        type="button"
        className="min-h-11 rounded-xl border border-jp-border px-4 text-sm disabled:opacity-60"
        disabled={!isLive || mediaBusy || (!logoFile && !faviconFile)}
        data-testid="company-branding-media-save"
        onClick={async () => {
          setMediaBusy(true);
          setMediaError(null);
          const formData = new FormData();
          if (logoFile) formData.append("logo", logoFile);
          if (faviconFile) formData.append("favicon", faviconFile);
          const result = await updateOrganizationBrandingMedia(formData);
          setMediaBusy(false);
          if (!result.ok) {
            setMediaError(result.message ?? "Logo/favicon upload failed.");
            return;
          }
          setLogoFile(null);
          setFaviconFile(null);
          const payload = ("data" in result ? result.data : result) as { organization?: Record<string, unknown> };
          const org = payload.organization ?? {};
          setLogoUrl(typeof org.logo_url === "string" ? org.logo_url : logoUrl);
          setFaviconUrl(typeof org.favicon_url === "string" ? org.favicon_url : faviconUrl);
          setSuccess("Branding media saved through the existing branding store.");
          router.refresh();
        }}
      >
        {mediaBusy ? "Uploading…" : "Upload logo / favicon"}
      </button>
      <p className="text-xs text-jp-muted">
        Live production logo replacement is a publishing action. Prefer a non-active media test before replacing the public logo.
      </p>
      <div className="grid gap-3 sm:grid-cols-2">
        {field("display_name", "JetPakistan display name")}
        {field("legal_name", "Legal / company name")}
        {field("support_email", "Support email")}
        {field("support_phone", "Support phone")}
        {field("website_url", "Website")}
        {field("timezone", "Timezone")}
        {field("city", "City")}
        {field("country", "Country")}
      </div>
      <label className="block text-xs font-medium text-jp-muted">
        Business address
        <textarea
          className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
          rows={3}
          value={form.office_address}
          onChange={(e) => setForm((prev) => ({ ...prev, office_address: e.target.value }))}
          disabled={!isLive}
        />
      </label>
      <button
        type="button"
        className="min-h-11 rounded-xl bg-jp-accent px-4 text-sm text-white disabled:opacity-60"
        disabled={!isLive || saving}
        onClick={async () => {
          setSaving(true);
          setError(null);
          setSuccess(null);
          const result = await updateOrganizationProfile(form);
          setSaving(false);
          if (!result.ok) {
            setError(result.message ?? "Save failed");
            return;
          }
          setSuccess("Organization profile saved. Current values will match this profile after reload.");
          const reloaded = await loadOrganizationProfile();
          if (reloaded.ok) {
            const payload = ("data" in reloaded ? reloaded.data : reloaded) as { organization?: Record<string, unknown> };
            const org = payload.organization ?? {};
            setForm({
              display_name: String(org.display_name ?? form.display_name),
              legal_name: String(org.legal_name ?? form.legal_name),
              support_email: String(org.support_email ?? form.support_email),
              support_phone: String(org.support_phone ?? form.support_phone),
              website_url: String(org.website_url ?? form.website_url),
              office_address: String(org.office_address ?? form.office_address),
              city: String(org.city ?? form.city),
              country: String(org.country ?? form.country),
              timezone: String(org.timezone ?? form.timezone),
            });
          }
          router.refresh();
        }}
      >
        {saving ? "Saving…" : "Save organization profile"}
      </button>
    </section>
  );
}
