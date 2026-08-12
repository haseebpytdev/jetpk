"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import {
  fetchAgentAgency,
  fetchAgentProfile,
  updateAgentAgency,
  updateAgentPersonalProfile,
} from "../services/agent-dashboard-api";
import { AgentDashboardErrorState, AgentDashboardShell } from "../shell/AgentDashboardShell";
import type { AgentAgencyProfile, AgentProfile } from "../types";
import type { PublicSession } from "@/types/session";
import { formatAgencyFieldValue, pickAgencyDisplayFields } from "./agency-display";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2 text-jp-sm text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus disabled:bg-jp-surface-muted";

export function AgentProfilePage({ session }: { session: PublicSession }) {
  const [profile, setProfile] = useState<AgentProfile | null>(null);
  const [agency, setAgency] = useState<AgentAgencyProfile | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [agencySubmitting, setAgencySubmitting] = useState(false);

  const load = async () => {
    setLoading(true);
    setError(null);
    const [profileResult, agencyResult] = await Promise.all([fetchAgentProfile(), fetchAgentAgency()]);
    if (!profileResult.ok) {
      setError(profileResult.message);
      setProfile(null);
    } else {
      setProfile(profileResult.data);
    }
    if (agencyResult.ok) {
      setAgency(agencyResult.data);
    } else {
      setAgency(null);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting || !profile?.capabilities.can_edit_personal) return;
    setSubmitting(true);
    setError(null);
    setSuccess(null);
    const formData = new FormData(event.currentTarget);
    const result = await updateAgentPersonalProfile(formData);
    if (!result.ok) {
      setError(result.message);
    } else {
      setSuccess("Profile updated successfully.");
      await load();
    }
    setSubmitting(false);
  };

  const handleAgencySubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (agencySubmitting || !profile?.capabilities.can_edit_agency) return;
    setAgencySubmitting(true);
    setError(null);
    setSuccess(null);
    const formData = new FormData(event.currentTarget);
    const result = await updateAgentAgency(formData);
    if (!result.ok) {
      setError(result.message);
    } else {
      setSuccess("Agency profile updated successfully.");
      await load();
    }
    setAgencySubmitting(false);
  };

  const personal = profile?.personal_profile ?? {};
  const agencyDetails = (agency?.details ?? profile?.agency_profile ?? {}) as Record<string, unknown>;
  const displayFields = pickAgencyDisplayFields(agencyDetails);
  const logoUrl = typeof agencyDetails.logo_url === "string" ? agencyDetails.logo_url : null;

  return (
    <AgentDashboardShell session={session} title="Profile">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading profile…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={load} /> : null}
      {success ? (
        <p className="mb-4 rounded-jp-md border border-emerald-200 bg-emerald-50 p-3 text-jp-sm text-emerald-900" role="status">
          {success}
        </p>
      ) : null}
      {profile ? (
        <div className="space-y-6">
          <form
            onSubmit={handleSubmit}
            className="max-w-2xl space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-6"
            data-testid="agent-profile-form"
          >
            <div>
              <h2 className="font-display text-jp-h3 font-semibold text-jp-text">Personal details</h2>
              <p className="mt-1 text-jp-sm text-jp-muted">
                {profile.user.role_label} · Email verification: {profile.user.email_verified ? "Verified" : "Not verified"}
              </p>
            </div>
            <label className="block text-jp-sm font-medium text-jp-text">
              Full name
              <input name="name" defaultValue={profile.user.name} className={fieldClass} required disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm font-medium text-jp-text">
              Email
              <input name="email" type="email" defaultValue={profile.user.email} className={fieldClass} required disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm font-medium text-jp-text">
              Username
              <input name="username" defaultValue={profile.user.username} className={fieldClass} required disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm font-medium text-jp-text">
              Phone
              <input name="phone" defaultValue={String(personal.phone ?? "")} className={fieldClass} disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm font-medium text-jp-text">
              WhatsApp
              <input name="whatsapp" defaultValue={String(personal.whatsapp ?? "")} className={fieldClass} disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm font-medium text-jp-text">
              Country
              <select name="country_code" defaultValue={String(personal.country_code ?? "")} className={fieldClass} disabled={!profile.capabilities.can_edit_personal}>
                <option value="">Select country</option>
                {profile.countries.map((country) => (
                  <option key={country.code} value={country.code}>
                    {country.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-jp-sm font-medium text-jp-text">
              City
              <input name="city" defaultValue={String(personal.city ?? "")} className={fieldClass} disabled={!profile.capabilities.can_edit_personal} />
            </label>
            {profile.capabilities.can_edit_personal ? (
              <PrimaryButton type="submit" disabled={submitting}>
                {submitting ? "Saving…" : "Save profile"}
              </PrimaryButton>
            ) : null}
          </form>

          <section className="max-w-2xl space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-6" data-testid="agent-agency-section">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="font-display text-jp-h3 font-semibold text-jp-text">Agency details</h2>
                <p className="mt-1 text-jp-sm text-jp-muted">Business profile for your agency workspace.</p>
              </div>
              {logoUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={logoUrl} alt="" className="h-14 w-14 rounded-jp-md border border-jp-border object-contain bg-jp-surface-muted" />
              ) : (
                <div className="flex h-14 w-14 items-center justify-center rounded-jp-md border border-dashed border-jp-border bg-jp-surface-muted text-jp-xs text-jp-muted">
                  Logo
                </div>
              )}
            </div>

            <dl className="grid gap-3 sm:grid-cols-2" data-testid="agent-agency-details">
              {displayFields.map((field) => (
                <div key={field.key} className="min-w-0 rounded-jp-md border border-jp-border/70 bg-jp-surface-muted/40 px-3 py-2.5">
                  <dt className="text-[0.68rem] font-semibold uppercase tracking-wide text-jp-muted">{field.label}</dt>
                  <dd className="mt-1 truncate text-jp-sm font-medium text-jp-text" title={field.value}>
                    {field.value}
                  </dd>
                </div>
              ))}
            </dl>

            {profile.capabilities.can_edit_agency ? (
              <form onSubmit={handleAgencySubmit} className="space-y-4 border-t border-jp-border pt-4" data-testid="agent-agency-edit-form" encType="multipart/form-data">
                <h3 className="text-jp-sm font-semibold text-jp-text">Update agency profile</h3>
                <label className="block text-jp-sm font-medium text-jp-text">
                  Agency name
                  <input
                    name="agency_name"
                    defaultValue={String(agencyDetails.agency_name ?? "")}
                    className={fieldClass}
                    required
                  />
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                  <label className="block text-jp-sm font-medium text-jp-text">
                    Phone
                    <input name="phone" defaultValue={String(agencyDetails.phone ?? "")} className={fieldClass} />
                  </label>
                  <label className="block text-jp-sm font-medium text-jp-text">
                    Email
                    <input name="email" type="email" defaultValue={String(agencyDetails.email ?? "")} className={fieldClass} />
                  </label>
                  <label className="block text-jp-sm font-medium text-jp-text">
                    City
                    <input name="city" defaultValue={String(agencyDetails.city ?? "")} className={fieldClass} />
                  </label>
                  <label className="block text-jp-sm font-medium text-jp-text">
                    Country
                    <input name="country" defaultValue={String(agencyDetails.country ?? "")} className={fieldClass} />
                  </label>
                </div>
                <label className="block text-jp-sm font-medium text-jp-text">
                  License number
                  <input name="license_number" defaultValue={String(agencyDetails.license_number ?? "")} className={fieldClass} />
                </label>
                <label className="block text-jp-sm font-medium text-jp-text">
                  Address
                  <input name="address" defaultValue={String(agencyDetails.address ?? "")} className={fieldClass} />
                </label>
                <label className="block text-jp-sm font-medium text-jp-text">
                  Agency logo
                  <input name="logo" type="file" accept="image/*" className={fieldClass} />
                  <span className="mt-1 block text-jp-xs text-jp-muted">JPG, PNG, or WebP up to 2MB.</span>
                </label>
                <div className="flex flex-wrap gap-2">
                  <PrimaryButton type="submit" disabled={agencySubmitting}>
                    {agencySubmitting ? "Saving…" : "Save agency"}
                  </PrimaryButton>
                  <Link href="/agent/agency">
                    <SecondaryButton type="button">View agency page</SecondaryButton>
                  </Link>
                </div>
              </form>
            ) : null}
          </section>
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}

// Re-export helper for tests
export { formatAgencyFieldValue };
