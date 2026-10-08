import type { ActionCard } from "@/types/dashboard";

type QueueTarget = {
  path: string;
};

/**
 * Authoritative Next dashboard destinations for live overview operational queues.
 * Keys align with AgencyDashboardService::buildNeedsAttention().
 */
const QUEUE_TARGETS: Record<string, QueueTarget> = {
  pending_deposits: { path: "/deposits" },
  unassigned: { path: "/operations/inbox" },
  payment_review: { path: "/payments?reconciliation=pending_review" },
  supplier_pnr_pending: { path: "/pnrs" },
  ticketing_pending: { path: "/tickets" },
  cancellations_pending: { path: "/operations/review" },
  refunds_pending: { path: "/operations/execution" },
  failed_notifications: { path: "/notifications/failures" },
  manual_review: { path: "/operations/inbox" },
  needs_action: { path: "/bookings" },
  supplier_failures: { path: "/operations/inbox" },
  review_new_bookings: { path: "/bookings" },
  record_payment: { path: "/payments?reconciliation=pending_review" },
};

export type OperationalQueueRouteInput = Pick<ActionCard, "key" | "laravelRoute" | "queue">;

export function dashboardHrefForOperationalQueue(card: OperationalQueueRouteInput): string | null {
  const byKey = QUEUE_TARGETS[card.key];
  if (byKey) {
    return byKey.path;
  }

  if (card.laravelRoute === "admin.agent-deposits.index") {
    return "/deposits";
  }
  if (card.laravelRoute === "admin.operations.inbox") {
    return "/operations/inbox";
  }
  if (card.laravelRoute === "admin.payments" || card.laravelRoute === "admin.payments.index") {
    return "/payments";
  }
  if (card.laravelRoute === "admin.bookings" && card.queue === "payment_review") {
    return "/payments?reconciliation=pending_review";
  }
  if (card.laravelRoute === "admin.reports" || card.laravelRoute === "admin.reports.index") {
    return "/reports";
  }
  if (card.laravelRoute === "admin.api-settings" || card.laravelRoute === "admin.api-settings.index") {
    return "/api-connections";
  }
  if (card.laravelRoute === "admin.agent-applications.index") {
    return "/agents";
  }
  if (card.laravelRoute === "admin.settings.communications.delivery-log.index") {
    return "/notifications/failures";
  }

  return null;
}

export function dashboardHrefForBookingId(bookingId: string): string {
  return `/bookings/${encodeURIComponent(bookingId)}`;
}
