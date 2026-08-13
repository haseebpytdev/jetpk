"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadOrganizationProfile, updateOrganizationProfile } from "@/services/operational-api";

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
  const [form, setForm] = useState<OrgForm>(empty);
  const [logoUrl, setLogoUrl] = useState<string | null>(null);
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
      <h3 className="text-sm font-semibold text-gray-900">Organization / company profile</h3>
      <p className="text-xs text-jp-muted">
        Company identity is separate from the signed-in Admin profile. Logo and favicon uploads remain available through branding media.
      </p>
      {loading ? <p className="text-sm text-jp-muted">Loading organization…</p> : null}
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      {logoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={logoUrl} alt="Organization logo" className="h-12 w-auto" />
      ) : null}
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
          setSuccess("Organization profile saved.");
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
        }}
      >
        {saving ? "Saving…" : "Save organization profile"}
      </button>
    </section>
  );
}
