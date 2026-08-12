"use client";

import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { customerApiErrorMessage, fetchCustomerProfile, updateCustomerProfile } from "../services/customer-dashboard-api";
import { CustomerDashboardErrorState, CustomerDashboardShell } from "../shell/CustomerDashboardShell";
import type { CustomerProfile } from "../types";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2 text-jp-sm text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus";

export function CustomerProfilePage({ session }: { session: PublicSession }) {
  const [profile, setProfile] = useState<CustomerProfile | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const load = async () => {
    setLoading(true);
    const result = await fetchCustomerProfile();
    if (!result.ok) setError(customerApiErrorMessage(result));
    else setProfile(result.data);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    setSuccess(null);
    const formData = new FormData(event.currentTarget);
    const result = await updateCustomerProfile(formData);
    if (!result.ok) {
      setError(customerApiErrorMessage(result));
    } else {
      setSuccess("Profile updated successfully.");
      await load();
    }
    setSubmitting(false);
  };

  return (
    <CustomerDashboardShell session={session} title="Profile">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading profile…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {success ? (
        <p className="mb-4 rounded-jp-md border border-emerald-200 bg-emerald-50 p-3 text-jp-sm text-emerald-900" role="status">
          {success}
        </p>
      ) : null}
      {profile ? (
        <form onSubmit={handleSubmit} className="max-w-2xl space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-6" data-testid="customer-profile-form">
          <div>
            <h2 className="font-display text-jp-h3 font-semibold text-jp-text">Personal details</h2>
            <p className="mt-1 text-jp-sm text-jp-muted">
              Email verification: {profile.user.email_verified ? "Verified" : "Not verified"}
            </p>
          </div>
          <label className="block text-jp-sm font-medium text-jp-text">
            Full name
            <input name="name" defaultValue={profile.user.name} className={fieldClass} required />
          </label>
          <label className="block text-jp-sm font-medium text-jp-text">
            Email
            <input name="email" type="email" defaultValue={profile.user.email} className={fieldClass} required />
          </label>
          <label className="block text-jp-sm font-medium text-jp-text">
            Username
            <input name="username" defaultValue={profile.user.username} className={fieldClass} required />
          </label>
          <label className="block text-jp-sm font-medium text-jp-text">
            Phone
            <input name="phone" defaultValue={profile.profile.phone ?? ""} className={fieldClass} />
          </label>
          <label className="block text-jp-sm font-medium text-jp-text">
            City
            <input name="city" defaultValue={profile.profile.city ?? ""} className={fieldClass} />
          </label>
          <PrimaryButton type="submit" disabled={submitting}>
            {submitting ? "Saving…" : "Save profile"}
          </PrimaryButton>
        </form>
      ) : null}
    </CustomerDashboardShell>
  );
}
