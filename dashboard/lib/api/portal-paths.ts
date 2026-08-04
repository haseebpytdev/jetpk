import { getLaravelApiBase } from "@/lib/read-only/laravel/api-base";
import type { DashboardPortal } from "@/lib/portal-path";

export function laravelPortalPath(portal: DashboardPortal, path: string): string {
  const base = getLaravelApiBase().replace(/\/$/, "");
  const normalized = path.startsWith("/") ? path : `/${path}`;
  return `${base}/${portal}${normalized}`;
}

export function paymentVerifyPath(portal: DashboardPortal, paymentId: string): string {
  return laravelPortalPath(portal, `/bookings/payments/${encodeURIComponent(paymentId)}/verify?format=json`);
}

export function paymentRejectPath(portal: DashboardPortal, paymentId: string): string {
  return laravelPortalPath(portal, `/bookings/payments/${encodeURIComponent(paymentId)}/reject?format=json`);
}

export function depositApprovePath(depositId: string): string {
  return laravelPortalPath("admin", `/agent-deposits/${encodeURIComponent(depositId)}/approve?format=json`);
}

export function depositRejectPath(depositId: string): string {
  return laravelPortalPath("admin", `/agent-deposits/${encodeURIComponent(depositId)}/reject?format=json`);
}

export function cancellationProcessPath(portal: DashboardPortal, cancellationRequestId: string): string {
  return laravelPortalPath(
    portal,
    `/bookings/cancellations/${encodeURIComponent(cancellationRequestId)}/process?format=json`,
  );
}

export function refundMarkPaidPath(portal: DashboardPortal, refundId: string): string {
  return laravelPortalPath(portal, `/bookings/refunds/${encodeURIComponent(refundId)}/mark-paid?format=json`);
}

export function issueTicketPath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/issue-ticket?format=json`);
}
