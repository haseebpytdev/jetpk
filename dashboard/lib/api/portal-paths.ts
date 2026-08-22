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

export function cancellationApprovePath(portal: DashboardPortal, cancellationRequestId: string): string {
  return laravelPortalPath(
    portal,
    `/bookings/cancellations/${encodeURIComponent(cancellationRequestId)}/approve?format=json`,
  );
}

export function cancellationRejectPath(portal: DashboardPortal, cancellationRequestId: string): string {
  return laravelPortalPath(
    portal,
    `/bookings/cancellations/${encodeURIComponent(cancellationRequestId)}/reject?format=json`,
  );
}

export function refundApprovePath(portal: DashboardPortal, refundId: string): string {
  return laravelPortalPath(portal, `/bookings/refunds/${encodeURIComponent(refundId)}/approve?format=json`);
}

export function refundRejectPath(portal: DashboardPortal, refundId: string): string {
  return laravelPortalPath(portal, `/bookings/refunds/${encodeURIComponent(refundId)}/reject?format=json`);
}

export function bookingNotesPath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/notes?format=json`);
}

export function bookingContactPath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/contact?format=json`);
}

export function cmsPageStorePath(): string {
  return laravelPortalPath("admin", "/cms-pages?format=json");
}

export function cmsPageUpdatePath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}?format=json`);
}

export function cmsPageArchivePath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}/archive?format=json`);
}

export function cmsPageDuplicatePath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}/duplicate?format=json`);
}

export function cmsPageDestroyPath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}?format=json`);
}

export function cmsPagePreviewDraftPath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}/preview-draft?format=json`);
}

export function cmsPagePreviewPath(pageId: string, theme: string, viewport: string): string {
  return laravelPortalPath(
    "admin",
    `/cms-pages/${encodeURIComponent(pageId)}/preview?draft=1&theme=${encodeURIComponent(theme)}&viewport=${encodeURIComponent(viewport)}`,
  );
}

export function bookingAssignStaffPath(bookingId: string): string {
  return laravelPortalPath("admin", `/bookings/${encodeURIComponent(bookingId)}/assign-staff?format=json`);
}

export function cancellationStorePath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/cancellations?format=json`);
}

export function refundStorePath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/refunds?format=json`);
}

export function paymentStorePath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/payments?format=json`);
}

export function userActivatePath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(userId)}/activate?format=json`);
}

export function userSuspendPath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(userId)}/suspend?format=json`);
}

export function agencyUserRolePath(agencyId: string, userId: string): string {
  return laravelPortalPath(
    "admin",
    `/agencies/${encodeURIComponent(agencyId)}/users/${encodeURIComponent(userId)}/agency-role?format=json`,
  );
}

export function agencyUserPermissionsPath(agencyId: string, userId: string): string {
  return laravelPortalPath(
    "admin",
    `/agencies/${encodeURIComponent(agencyId)}/users/${encodeURIComponent(userId)}/agent-permissions?format=json`,
  );
}

export function agencyUserPermissionsApplyTemplatePath(agencyId: string, userId: string): string {
  return laravelPortalPath(
    "admin",
    `/agencies/${encodeURIComponent(agencyId)}/users/${encodeURIComponent(userId)}/agent-permissions/apply-template?format=json`,
  );
}

export function agencyPrefixPath(agencyId: string): string {
  return laravelPortalPath("admin", `/agencies/${encodeURIComponent(agencyId)}/prefix?format=json`);
}

export function agentApplicationApprovePath(applicationId: string): string {
  return laravelPortalPath("admin", `/agent-applications/${encodeURIComponent(applicationId)}/approve?format=json`);
}

export function agentApplicationRejectPath(applicationId: string): string {
  return laravelPortalPath("admin", `/agent-applications/${encodeURIComponent(applicationId)}/reject?format=json`);
}

export function agentApplicationNeedsMoreInfoPath(applicationId: string): string {
  return laravelPortalPath(
    "admin",
    `/agent-applications/${encodeURIComponent(applicationId)}/needs-more-info?format=json`,
  );
}

export function supportTicketAssignPath(ticketId: string): string {
  return laravelPortalPath("admin", `/support/tickets/${encodeURIComponent(ticketId)}/assign?format=json`);
}

export function supportTicketForwardPath(ticketId: string): string {
  return laravelPortalPath("admin", `/support/tickets/${encodeURIComponent(ticketId)}/forward?format=json`);
}

export function supportTicketReplyPath(portal: DashboardPortal, ticketId: string): string {
  return laravelPortalPath(portal, `/support/tickets/${encodeURIComponent(ticketId)}/reply?format=json`);
}

export function supportTicketStatusPath(portal: DashboardPortal, ticketId: string): string {
  return laravelPortalPath(portal, `/support/tickets/${encodeURIComponent(ticketId)}/status?format=json`);
}

export function commissionEntryApprovePath(entryId: string): string {
  return laravelPortalPath("admin", `/commissions/entries/${encodeURIComponent(entryId)}/approve?format=json`);
}

export function commissionEntryRejectPath(entryId: string): string {
  return laravelPortalPath("admin", `/commissions/entries/${encodeURIComponent(entryId)}/reject?format=json`);
}

export function groupBookingVerifyPaymentPath(groupBookingId: string): string {
  return laravelPortalPath(
    "admin",
    `/group-bookings/${encodeURIComponent(groupBookingId)}/verify-payment?format=json`,
  );
}

export function groupBookingRejectPaymentPath(groupBookingId: string): string {
  return laravelPortalPath(
    "admin",
    `/group-bookings/${encodeURIComponent(groupBookingId)}/reject-payment?format=json`,
  );
}

export function financeAdjustmentStorePath(): string {
  return laravelPortalPath("admin", "/finance/adjustments?format=json");
}

export function financeAdjustmentReversePath(walletTransactionId: string): string {
  return laravelPortalPath(
    "admin",
    `/finance/adjustments/${encodeURIComponent(walletTransactionId)}/reverse?format=json`,
  );
}

export function markupLookupsPath(type: string, q: string): string {
  return laravelPortalPath("admin", `/markups/lookups?format=json&type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`);
}

export function markupStorePath(): string {
  return laravelPortalPath("admin", "/markups?format=json");
}

export function markupUpdatePath(markupId: string): string {
  return laravelPortalPath("admin", `/markups/${encodeURIComponent(markupId)}?format=json`);
}

export function markupTogglePath(markupId: string): string {
  return laravelPortalPath("admin", `/markups/${encodeURIComponent(markupId)}/toggle-status?format=json`);
}

export function markupDestroyPath(markupId: string): string {
  return laravelPortalPath("admin", `/markups/${encodeURIComponent(markupId)}?format=json`);
}

export function apiSettingsIndexPath(): string {
  return laravelPortalPath("admin", "/api-settings?format=json");
}

export function apiSettingsStorePath(): string {
  return laravelPortalPath("admin", "/api-settings?format=json");
}

export function apiSettingsUpdatePath(connectionId: string): string {
  return laravelPortalPath("admin", `/api-settings/${encodeURIComponent(connectionId)}?format=json`);
}

export function apiSettingsTogglePath(connectionId: string): string {
  return laravelPortalPath("admin", `/api-settings/${encodeURIComponent(connectionId)}/toggle-status?format=json`);
}

export function apiSettingsTestPath(connectionId: string): string {
  return laravelPortalPath("admin", `/api-settings/${encodeURIComponent(connectionId)}/test?format=json`);
}

export function integrationsIndexPath(category?: string): string {
  const qs = category && category !== "all" ? `&category=${encodeURIComponent(category)}` : "";
  return laravelPortalPath("admin", `/integrations?format=json${qs}`);
}

export function integrationShowPath(code: string): string {
  return laravelPortalPath("admin", `/integrations/${encodeURIComponent(code)}?format=json`);
}

export function integrationUpdatePath(code: string): string {
  return laravelPortalPath("admin", `/integrations/${encodeURIComponent(code)}?format=json`);
}

export function integrationActivatePath(code: string): string {
  return laravelPortalPath("admin", `/integrations/${encodeURIComponent(code)}/activate?format=json`);
}

export function integrationDeactivatePath(code: string): string {
  return laravelPortalPath("admin", `/integrations/${encodeURIComponent(code)}/deactivate?format=json`);
}

export function integrationTestConnectionPath(code: string): string {
  return laravelPortalPath("admin", `/integrations/${encodeURIComponent(code)}/test-connection?format=json`);
}

export function integrationTestPaymentPath(code: string): string {
  return laravelPortalPath("admin", `/integrations/${encodeURIComponent(code)}/test-payment?format=json`);
}

export function notificationEventsUpdatePath(): string {
  return laravelPortalPath("admin", "/settings/communications/notification-events?format=json");
}

export function brandingSettingsPath(): string {
  return laravelPortalPath("admin", "/settings/branding?format=json");
}

export function usersStorePath(): string {
  return laravelPortalPath("admin", "/users?format=json");
}

export function userInvitePath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(userId)}/send-invite?format=json`);
}

export function userResetPasswordPath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(userId)}/reset-password-link?format=json`);
}

export function userUpdatePath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(userId)}?format=json`);
}

export function mediaLibraryIndexPath(): string {
  return laravelPortalPath("admin", "/settings/media?format=json");
}

export function mediaLibraryStorePath(): string {
  return laravelPortalPath("admin", "/settings/media?format=json");
}

export function mediaLibraryDestroyPath(mediaId: string): string {
  return laravelPortalPath("admin", `/settings/media/${encodeURIComponent(mediaId)}?format=json`);
}

export function mediaLibraryUpdatePath(mediaId: string): string {
  return laravelPortalPath("admin", `/settings/media/${encodeURIComponent(mediaId)}?format=json`);
}

export function pageSettingsEditPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}?format=json`);
}

export function pageSettingsPublishPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}/publish?format=json`);
}
