import type { SessionBootstrap } from "@/features/auth/types";
import { redirect } from "next/navigation";
import { sanitizeDashboardUrl } from "../utils/dashboard-allowlist";

const DISABLED_STATUSES = new Set(["suspended", "inactive"]);

/**
 * Shared portal gate checks derived from Laravel session bootstrap.
 */
export function assertSessionUsable(bootstrap: SessionBootstrap): void {
  if (bootstrap.session_usable === false) {
    redirect("/access-denied?reason=account-disabled");
  }

  const status = bootstrap.account_status ?? "active";
  if (DISABLED_STATUSES.has(status)) {
    redirect("/access-denied?reason=account-disabled");
  }
}

export function redirectUnauthenticated(bootstrap: SessionBootstrap): void {
  if (!bootstrap.authenticated || !bootstrap.user) {
    redirect(bootstrap.session_expired ? "/login?reason=session-expired" : "/login");
  }
}

export function redirectPendingOtp(bootstrap: SessionBootstrap): void {
  if (bootstrap.requires_otp) {
    redirect("/login/otp");
  }
}

export function redirectPasswordChangeRequired(bootstrap: SessionBootstrap): void {
  if (bootstrap.requires_password_change) {
    redirect("/password/force-change");
  }
}

export function redirectEmailVerificationRequired(bootstrap: SessionBootstrap): void {
  if (bootstrap.requires_email_verification) {
    redirect("/verify-email");
  }
}

export function redirectWrongRole(
  bootstrap: SessionBootstrap,
  allowedAccountTypes: Set<string>,
): void {
  const accountType = bootstrap.user?.account_type ?? bootstrap.role ?? null;
  if (!accountType || !allowedAccountTypes.has(accountType)) {
    const destination = sanitizeDashboardUrl(bootstrap.dashboard_url, "/access-denied");
    redirect(destination);
  }
}
