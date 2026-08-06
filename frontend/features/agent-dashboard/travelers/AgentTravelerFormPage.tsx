"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  agentApiErrorMessage,
  fetchAgentTravelerForm,
  saveAgentTraveler,
} from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentSavedTraveler } from "../types";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function AgentTravelerFormPage({
  session,
  travelerId,
}: {
  session: PublicSession;
  travelerId?: number;
}) {
  const [traveler, setTraveler] = useState<AgentSavedTraveler | null>(null);
  const [countries, setCountries] = useState<Array<{ code: string; name: string }>>([]);
  const [error, setError] = useState<string | null>(null);
  const [denied, setDenied] = useState(false);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    void (async () => {
      setLoading(true);
      const result = await fetchAgentTravelerForm(travelerId);
      if (!result.ok) {
        if (result.status === 403) setDenied(true);
        else setError(agentApiErrorMessage(result));
      } else {
        setTraveler(result.data.traveler);
        setCountries(result.data.countries);
      }
      setLoading(false);
    })();
  }, [travelerId]);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const payload: Record<string, string | boolean> = {};
    form.forEach((value, key) => {
      payload[key] = String(value);
    });
    payload.is_default = form.get("is_default") === "on";

    const result = await saveAgentTraveler(payload, {
      travelerId,
      method: travelerId ? "PATCH" : "POST",
    });
    if (!result.ok) {
      setError(agentApiErrorMessage(result));
      setSubmitting(false);
      return;
    }

    window.location.assign(result.data.redirect_url);
  };

  if (denied) {
    return (
      <AgentDashboardShell session={session} title={travelerId ? "Edit traveler" : "Add traveler"}>
        <PermissionDeniedState message="You do not have permission to manage travelers." />
      </AgentDashboardShell>
    );
  }

  return (
    <AgentDashboardShell session={session} title={travelerId ? "Edit traveler" : "Add traveler"}>
      <div className="mb-4">
        <Link href="/agent/travelers" className="text-jp-sm text-jp-primary">
          Back to travelers
        </Link>
      </div>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading form…</p> : null}
      {error && !traveler ? <AgentDashboardErrorState message={error} /> : null}

      {traveler ? (
        <form
          onSubmit={handleSubmit}
          className="space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-4"
          data-testid="agent-traveler-form"
        >
          {error ? (
            <p className="text-jp-sm text-red-700" role="alert">
              {error}
            </p>
          ) : null}
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block text-jp-sm">
              Title
              <select name="title" defaultValue={traveler.title} className={fieldClass}>
                {["Mr", "Mrs", "Ms", "Miss", "Dr", "Mx"].map((title) => (
                  <option key={title} value={title}>
                    {title}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-jp-sm">
              Gender
              <select name="gender" defaultValue={traveler.gender} className={fieldClass}>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </label>
            <label className="block text-jp-sm">
              First name
              <input name="first_name" defaultValue={traveler.first_name} className={fieldClass} required />
            </label>
            <label className="block text-jp-sm">
              Last name
              <input name="last_name" defaultValue={traveler.last_name} className={fieldClass} required />
            </label>
            <label className="block text-jp-sm">
              Date of birth
              <input
                name="date_of_birth"
                type="date"
                defaultValue={traveler.date_of_birth ?? ""}
                className={fieldClass}
                required
              />
            </label>
            <label className="block text-jp-sm">
              Nationality
              <select name="nationality" defaultValue={traveler.nationality} className={fieldClass} required>
                {countries.map((country) => (
                  <option key={country.code} value={country.code}>
                    {country.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-jp-sm">
              Document type
              <select name="document_type" defaultValue={traveler.document_type} className={fieldClass} required>
                <option value="passport">Passport</option>
                <option value="national_id">National ID</option>
              </select>
            </label>
            <label className="block text-jp-sm">
              Document number
              <input name="document_number" defaultValue={traveler.document_number ?? ""} className={fieldClass} />
            </label>
            <label className="block text-jp-sm">
              Document expiry
              <input
                name="document_expiry"
                type="date"
                defaultValue={traveler.document_expiry ?? ""}
                className={fieldClass}
              />
            </label>
            <label className="block text-jp-sm">
              Issuing country
              <select
                name="issuing_country"
                defaultValue={traveler.issuing_country ?? traveler.nationality}
                className={fieldClass}
              >
                <option value="">Select</option>
                {countries.map((country) => (
                  <option key={country.code} value={country.code}>
                    {country.name}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <label className="flex items-center gap-2 text-jp-sm">
            <input type="checkbox" name="is_default" defaultChecked={traveler.is_default} />
            Set as default traveler
          </label>
          <div className="flex gap-2">
            <PrimaryButton type="submit" disabled={submitting}>
              {submitting ? "Saving…" : travelerId ? "Update traveler" : "Save traveler"}
            </PrimaryButton>
            <Link
              href="/agent/travelers"
              className="inline-flex items-center rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm"
            >
              Cancel
            </Link>
          </div>
        </form>
      ) : null}
    </AgentDashboardShell>
  );
}
