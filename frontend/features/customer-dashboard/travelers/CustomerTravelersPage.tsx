"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  customerApiErrorMessage,
  deleteSavedTraveler,
  fetchSavedTravelerForm,
  fetchSavedTravelers,
  saveSavedTraveler,
} from "../services/customer-dashboard-api";
import {
  CustomerDashboardErrorState,
  CustomerDashboardShell,
  CustomerEmptyState,
} from "../shell/CustomerDashboardShell";
import type { CustomerSavedTraveler } from "../types";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function CustomerTravelersPage({ session }: { session: PublicSession }) {
  const [travelers, setTravelers] = useState<CustomerSavedTraveler[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchSavedTravelers();
    if (!result.ok) setError(customerApiErrorMessage(result));
    else setTravelers(result.data.travelers);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const handleDelete = async (travelerId: number) => {
    if (!window.confirm("Remove this saved traveler?")) return;
    const result = await deleteSavedTraveler(travelerId);
    if (!result.ok) setError(customerApiErrorMessage(result));
    else await load();
  };

  return (
    <CustomerDashboardShell session={session} title="Saved travelers">
      <div className="mb-4">
        <PrimaryButton
          type="button"
          onClick={() => {
            setEditingId(null);
            setShowForm((value) => !value);
          }}
        >
          {showForm ? "Hide form" : "Add traveler"}
        </PrimaryButton>
      </div>

      {showForm ? (
        <TravelerForm
          travelerId={editingId}
          onSaved={async () => {
            setShowForm(false);
            setEditingId(null);
            await load();
          }}
          onCancel={() => {
            setShowForm(false);
            setEditingId(null);
          }}
        />
      ) : null}

      {loading ? <p className="text-jp-sm text-jp-muted">Loading travelers…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {!loading && !error && travelers.length === 0 ? (
        <CustomerEmptyState title="No saved travelers" description="Save traveler details for faster future bookings." />
      ) : null}

      <div className="space-y-3" data-testid="customer-travelers-list">
        {travelers.map((traveler) => (
          <article key={traveler.id} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">
                  {traveler.title} {traveler.first_name} {traveler.last_name}
                </p>
                <p className="text-jp-sm text-jp-muted">
                  {traveler.nationality} · {traveler.document_type}
                  {traveler.is_default ? " · Default" : ""}
                </p>
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  className="text-jp-sm text-jp-primary"
                  onClick={() => {
                    setEditingId(traveler.id);
                    setShowForm(true);
                  }}
                >
                  Edit
                </button>
                {traveler.id ? (
                  <button type="button" className="text-jp-sm text-red-700" onClick={() => void handleDelete(traveler.id!)}>
                    Delete
                  </button>
                ) : null}
              </div>
            </div>
          </article>
        ))}
      </div>
    </CustomerDashboardShell>
  );
}

function TravelerForm({
  travelerId,
  onSaved,
  onCancel,
}: {
  travelerId: number | null;
  onSaved: () => void;
  onCancel: () => void;
}) {
  const [traveler, setTraveler] = useState<CustomerSavedTraveler | null>(null);
  const [countries, setCountries] = useState<Array<{ code: string; name: string }>>([]);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    void (async () => {
      const result = await fetchSavedTravelerForm(travelerId ?? undefined);
      if (result.ok) {
        setTraveler(result.data.traveler);
        setCountries(result.data.countries);
      }
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

    const result = await saveSavedTraveler(payload, {
      travelerId: travelerId ?? undefined,
      method: travelerId ? "PATCH" : "POST",
    });
    if (!result.ok) setError(customerApiErrorMessage(result));
    else onSaved();
    setSubmitting(false);
  };

  if (!traveler) return <p className="text-jp-sm text-jp-muted">Loading form…</p>;

  return (
    <form onSubmit={handleSubmit} className="mb-6 space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="traveler-form">
      {error ? <p className="text-jp-sm text-red-700" role="alert">{error}</p> : null}
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
          <input name="date_of_birth" type="date" defaultValue={traveler.date_of_birth ?? ""} className={fieldClass} required />
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
          <input name="document_expiry" type="date" defaultValue={traveler.document_expiry ?? ""} className={fieldClass} />
        </label>
        <label className="block text-jp-sm">
          Issuing country
          <select name="issuing_country" defaultValue={traveler.issuing_country ?? traveler.nationality} className={fieldClass}>
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
        <button type="button" className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm" onClick={onCancel}>
          Cancel
        </button>
      </div>
    </form>
  );
}
