import type { DashboardPortal } from "@/lib/portal-path";
import type { DashboardSessionSummary } from "@/services/session-service";

export type PortalSessionDenialCode =
  | "unauthenticated"
  | "forbidden"
  | "portal_mismatch"
  | "session_unavailable";

export class DashboardPortalSessionError extends Error {
  readonly code: PortalSessionDenialCode;

  constructor(code: PortalSessionDenialCode, message?: string) {
    super(message ?? code);
    this.name = "DashboardPortalSessionError";
    this.code = code;
  }
}

export function assertPortalSessionAuthorized(
  session: DashboardSessionSummary,
  portal: DashboardPortal,
): void {
  if (session.unavailable || session.sessionUsable === false) {
    throw new DashboardPortalSessionError("session_unavailable");
  }

  if (session.denialReason) {
    throw new DashboardPortalSessionError("forbidden");
  }

  if (session.portalType !== portal) {
    throw new DashboardPortalSessionError("portal_mismatch");
  }

  if (portal === "admin" && session.platformRole !== "platform_admin") {
    throw new DashboardPortalSessionError("forbidden");
  }

  if (portal === "staff" && session.platformRole !== "staff" && session.platformRole !== "platform_admin") {
    throw new DashboardPortalSessionError("forbidden");
  }
}
