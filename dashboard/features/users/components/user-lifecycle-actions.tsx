"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { activateUser, suspendUser } from "@/services/operational-api";

export function UserLifecycleActions({
  userId,
  status,
}: {
  userId: string;
  status: string;
}) {
  const isLive = useDashboardLiveMode();
  const [busy, setBusy] = useState<"activate" | "suspend" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [localStatus, setLocalStatus] = useState(status);

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="user-lifecycle-preview">
        User lifecycle actions are available in live dashboard mode only.
      </p>
    );
  }

  async function run(action: "activate" | "suspend") {
    setBusy(action);
    setError(null);
    const result = action === "activate" ? await activateUser(userId) : await suspendUser(userId);
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setLocalStatus(action === "activate" ? "active" : "suspended");
  }

  return (
    <div className="space-y-2" data-testid="user-lifecycle-actions">
      <p className="text-sm text-jp-muted">Status: {localStatus}</p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-sm text-white disabled:opacity-60"
          disabled={busy !== null || localStatus === "active"}
          onClick={() => run("activate")}
          data-testid="user-activate"
        >
          {busy === "activate" ? "Activating…" : "Activate"}
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-red-300 px-3 py-2 text-sm text-red-700 disabled:opacity-60"
          disabled={busy !== null || localStatus === "suspended"}
          onClick={() => run("suspend")}
          data-testid="user-suspend"
        >
          {busy === "suspend" ? "Suspending…" : "Suspend"}
        </button>
      </div>
    </div>
  );
}
