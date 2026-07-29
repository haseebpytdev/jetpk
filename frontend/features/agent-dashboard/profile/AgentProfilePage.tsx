"use client";

import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchAgentProfile, updateAgentPersonalProfile } from "../services/agent-dashboard-api";
import { AgentDashboardErrorState, AgentDashboardShell } from "../shell/AgentDashboardShell";
import type { AgentProfile } from "../types";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function AgentProfilePage({ session }: { session: PublicSession }) {
  const [profile, setProfile] = useState<AgentProfile | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const load = async () => {
    setLoading(true);
    const result = await fetchAgentProfile();
    if (!result.ok) setError(result.message);
    else setProfile(result.data);
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

  const personal = profile?.personal_profile ?? {};
  const agency = profile?.agency_profile ?? {};

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
            <p className="text-jp-sm text-jp-muted">
              {profile.user.role_label} · Email verification: {profile.user.email_verified ? "Verified" : "Not verified"}
            </p>
            <label className="block text-jp-sm">
              Full name
              <input name="name" defaultValue={profile.user.name} className={fieldClass} required disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm">
              Email
              <input name="email" type="email" defaultValue={profile.user.email} className={fieldClass} required disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm">
              Username
              <input name="username" defaultValue={profile.user.username} className={fieldClass} required disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm">
              Phone
              <input name="phone" defaultValue={String(personal.phone ?? "")} className={fieldClass} disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm">
              WhatsApp
              <input name="whatsapp" defaultValue={String(personal.whatsapp ?? "")} className={fieldClass} disabled={!profile.capabilities.can_edit_personal} />
            </label>
            <label className="block text-jp-sm">
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
            <label className="block text-jp-sm">
              City
              <input name="city" defaultValue={String(personal.city ?? "")} className={fieldClass} disabled={!profile.capabilities.can_edit_personal} />
            </label>
            {profile.capabilities.can_edit_personal ? (
              <PrimaryButton type="submit" disabled={submitting}>
                {submitting ? "Saving…" : "Save profile"}
              </PrimaryButton>
            ) : null}
          </form>

          {profile.capabilities.can_edit_agency ? (
            <section className="max-w-2xl space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-6" data-testid="agent-agency-section">
              <h2 className="text-jp-base font-semibold text-jp-text">Agency details</h2>
              <p className="text-jp-sm text-jp-muted">Agency profile editing is managed in the Laravel portal for now.</p>
              <dl className="grid gap-3 sm:grid-cols-2">
                {Object.entries(agency).map(([key, value]) =>
                  value != null && value !== "" ? (
                    <div key={key}>
                      <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">{key.replace(/_/g, " ")}</dt>
                      <dd className="text-jp-sm text-jp-text">{String(value)}</dd>
                    </div>
                  ) : null,
                )}
              </dl>
            </section>
          ) : null}
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
