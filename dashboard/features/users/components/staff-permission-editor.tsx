"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { updatePlatformUser } from "@/services/operational-api";
import type { User } from "@/types/access-control";

const STAFF_PERMISSIONS = [
  "staff.bookings.view",
  "staff.bookings.update_status",
  "staff.bookings.notes",
  "staff.payments.record",
  "staff.payments.verify",
  "staff.payments.reject",
  "staff.cancellations.create",
  "staff.cancellations.approve",
  "staff.cancellations.process",
  "staff.refunds.create",
  "staff.refunds.approve",
  "staff.refunds.mark_paid",
  "staff.refunds.reject",
  "staff.documents.generate",
  "staff.documents.download",
  "staff.ticketing.issue",
  "staff.support.view",
  "staff.support.reply",
  "staff.support.status",
  "staff.ledger.view",
  "staff.ledger.manage",
  "staff.ledger.adjust",
  "staff.reports.view",
  "staff.reports.export",
  "staff.page_settings.manage",
] as const;

function laravelAccountType(user: User): "staff" | null {
  if (user.profile.userType === "customer" || user.profile.userType === "bookingAgent" || user.profile.userType === "agentStaff" || user.profile.userType === "superAdministrator") {
    return null;
  }
  return "staff";
}

function laravelStatus(status: User["security"]["status"]): string {
  if (status === "suspended" || status === "locked" || status === "disabled") return "suspended";
  if (status === "invited" || status === "pendingVerification") return "invited";
  return "active";
}

export function StaffPermissionEditor({ user }: { user: User }) {
  const isLive = useDashboardLiveMode();
  const accountType = laravelAccountType(user);
  const [selected, setSelected] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  if (!isLive || accountType !== "staff") {
    return null;
  }

  return (
    <section className="space-y-2" data-testid="staff-permission-editor">
      <h3 className="text-sm font-semibold text-gray-900">Staff permissions</h3>
      <p className="text-xs text-jp-muted">
        Assignments are stored on the user record. System Platform Admin protection and last-admin lockout remain server-side.
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <div className="grid max-h-64 gap-1 overflow-auto rounded-lg border border-jp-border p-2 text-xs">
        {STAFF_PERMISSIONS.map((key) => (
          <label key={key} className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={selected.includes(key)}
              onChange={(e) => {
                setSelected((current) => (e.target.checked ? [...current, key] : current.filter((item) => item !== key)));
              }}
            />
            <span>{key.replace("staff.", "")}</span>
          </label>
        ))}
      </div>
      <button
        type="button"
        className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          setError(null);
          setSuccess(null);
          const result = await updatePlatformUser(user.id, {
            name: user.profile.fullName,
            email: user.contact.email,
            account_type: "staff",
            status: laravelStatus(user.security.status),
            department: user.profile.department,
            role_title: user.profile.jobTitle,
            staff_permissions_configured: true,
            staff_permissions: selected,
          });
          setBusy(false);
          if (!result.ok) {
            setError(result.message ?? "Permission update failed");
            return;
          }
          setSuccess("Staff permissions saved.");
        }}
      >
        {busy ? "Saving…" : "Save staff permissions"}
      </button>
    </section>
  );
}
