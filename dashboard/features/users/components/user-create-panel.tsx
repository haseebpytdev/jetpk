"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { createPlatformUser } from "@/services/operational-api";

export function UserCreatePanel({ accountType }: { accountType: "staff" | "customer" }) {
  const isLive = useDashboardLiveMode();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  if (!isLive) {
    return <p className="text-xs text-jp-muted">Create/invite is available in live dashboard mode only.</p>;
  }

  return (
    <div className="space-y-2 rounded-xl border border-jp-border p-4" data-testid="user-create-panel">
      <h2 className="text-sm font-semibold">Create {accountType === "staff" ? "staff" : "user"}</h2>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <div className="grid gap-2 sm:grid-cols-2">
        <input className="rounded-lg border border-jp-border px-2 py-1 text-sm" placeholder="Full name" value={name} onChange={(e) => setName(e.target.value)} />
        <input className="rounded-lg border border-jp-border px-2 py-1 text-sm" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} />
      </div>
      <button
        type="button"
        className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
        disabled={busy || !name.trim() || !email.trim()}
        onClick={async () => {
          setBusy(true);
          setError(null);
          setSuccess(null);
          const result = await createPlatformUser({
            name: name.trim(),
            email: email.trim(),
            account_type: accountType,
            status: "invited",
            send_invite: true,
          });
          setBusy(false);
          if (!result.ok) {
            setError(result.message ?? "Create failed");
            return;
          }
          setSuccess("User created. Invite sent when mail is configured.");
          setName("");
          setEmail("");
        }}
      >
        {busy ? "Creating…" : "Create and invite"}
      </button>
    </div>
  );
}
