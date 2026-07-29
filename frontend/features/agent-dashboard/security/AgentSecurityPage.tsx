"use client";

import { useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { updateAgentPassword } from "../services/agent-dashboard-api";
import { AgentDashboardErrorState, AgentDashboardShell } from "../shell/AgentDashboardShell";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function AgentSecurityPage({ session }: { session: PublicSession }) {
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    setSuccess(null);
    const form = new FormData(event.currentTarget);
    const result = await updateAgentPassword({
      current_password: String(form.get("current_password") ?? ""),
      password: String(form.get("password") ?? ""),
      password_confirmation: String(form.get("password_confirmation") ?? ""),
    });
    if (!result.ok) setError(result.message);
    else {
      setSuccess("Password updated successfully.");
      event.currentTarget.reset();
    }
    setSubmitting(false);
  };

  return (
    <AgentDashboardShell session={session} title="Security">
      {error ? <AgentDashboardErrorState message={error} /> : null}
      {success ? (
        <p className="mb-4 rounded-jp-md border border-emerald-200 bg-emerald-50 p-3 text-jp-sm text-emerald-900" role="status">
          {success}
        </p>
      ) : null}
      <form onSubmit={handleSubmit} className="max-w-xl space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-6" data-testid="agent-security-form">
        <label className="block text-jp-sm">
          Current password
          <input name="current_password" type="password" autoComplete="current-password" className={fieldClass} required />
        </label>
        <label className="block text-jp-sm">
          New password
          <input name="password" type="password" autoComplete="new-password" className={fieldClass} required />
        </label>
        <label className="block text-jp-sm">
          Confirm new password
          <input name="password_confirmation" type="password" autoComplete="new-password" className={fieldClass} required />
        </label>
        <PrimaryButton type="submit" disabled={submitting}>
          {submitting ? "Updating…" : "Change password"}
        </PrimaryButton>
      </form>
    </AgentDashboardShell>
  );
}
