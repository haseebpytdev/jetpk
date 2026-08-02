"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  agentApiErrorMessage,
  createAgentStaff,
  fetchAgentStaffCreateForm,
} from "../services/agent-dashboard-api";
import { AgentDashboardErrorState, AgentDashboardShell } from "../shell/AgentDashboardShell";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function AgentStaffCreatePage({ session }: { session: PublicSession }) {
  const router = useRouter();
  const [permissionLabels, setPermissionLabels] = useState<Record<string, string>>({});
  const [selected, setSelected] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    void (async () => {
      const result = await fetchAgentStaffCreateForm();
      if (result.ok) {
        setPermissionLabels(result.data.permission_labels);
        setSelected(result.data.default_permissions);
      }
    })();
  }, []);

  const togglePermission = (key: string) => {
    setSelected((current) => (current.includes(key) ? current.filter((item) => item !== key) : [...current, key]));
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const result = await createAgentStaff({
      name: String(form.get("name") ?? ""),
      email: String(form.get("email") ?? ""),
      phone: String(form.get("phone") ?? "") || undefined,
      password: String(form.get("password") ?? ""),
      permissions: selected,
    });
    if (!result.ok) {
      setError(agentApiErrorMessage(result));
      setSubmitting(false);
      return;
    }
    router.push("/agent/staff");
  };

  return (
    <AgentDashboardShell session={session} title="Add staff">
      <Link href="/agent/staff" className="mb-4 inline-block text-jp-sm text-jp-primary">
        Back to staff
      </Link>
      {error ? <AgentDashboardErrorState message={error} /> : null}
      <form className="max-w-xl space-y-4" onSubmit={handleSubmit} data-testid="agent-staff-create-form">
        <label className="block text-jp-sm">
          Name
          <input name="name" required className={fieldClass} />
        </label>
        <label className="block text-jp-sm">
          Email
          <input name="email" type="email" required className={fieldClass} />
        </label>
        <label className="block text-jp-sm">
          Phone
          <input name="phone" className={fieldClass} />
        </label>
        <label className="block text-jp-sm">
          Temporary password
          <input name="password" type="password" required minLength={8} className={fieldClass} />
        </label>
        <fieldset>
          <legend className="text-jp-sm font-semibold">Permissions</legend>
          <div className="mt-2 space-y-2">
            {Object.entries(permissionLabels).map(([key, label]) => (
              <label key={key} className="flex items-center gap-2 text-jp-sm">
                <input type="checkbox" checked={selected.includes(key)} onChange={() => togglePermission(key)} />
                {label}
              </label>
            ))}
          </div>
        </fieldset>
        <PrimaryButton type="submit" disabled={submitting}>
          {submitting ? "Creating…" : "Create staff member"}
        </PrimaryButton>
      </form>
    </AgentDashboardShell>
  );
}
