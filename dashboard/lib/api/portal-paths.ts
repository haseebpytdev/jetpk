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

export function supportTicketsIndexPath(query = ""): string {
  const suffix = query ? `&${query.replace(/^\?/, "").replace(/^&/, "")}` : "";
  return laravelPortalPath("admin", `/support/tickets?format=json${suffix}`);
}

export function supportTicketShowPath(ticketId: string): string {
  return laravelPortalPath("admin", `/support/tickets/${encodeURIComponent(ticketId)}?format=json`);
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

export function commissionsIndexPath(query = ""): string {
  const suffix = query ? `&${query.replace(/^\?/, "").replace(/^&/, "")}` : "";
  return laravelPortalPath("admin", `/commissions?format=json${suffix}`);
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
  return laravelPortalPath(
    "admin",
    `/api-settings/${encodeURIComponent(connectionId)}/toggle-status?format=json`,
  );
}

export function apiSettingsTestPath(connectionId: string): string {
  return laravelPortalPath("admin", `/api-settings/${encodeURIComponent(connectionId)}/test?format=json`);
}

export function brandingSettingsPath(): string {
  return laravelPortalPath("admin", "/settings/branding?format=json");
}

export function pageSettingsEditPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}?format=json`);
}

export function pageSettingsPublishPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}/publish?format=json`);
}

export function cmsPagesIndexPath(): string {
  return laravelPortalPath("admin", "/cms-pages?format=json");
}

export function cmsPageStorePath(): string {
  return laravelPortalPath("admin", "/cms-pages?format=json");
}

export function cmsPageEditPath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}/edit?format=json`);
}

export function cmsPageUpdatePath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}?format=json`);
}

export function cmsPageArchivePath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}/archive?format=json`);
}

export function cmsPageDestroyPath(pageId: string): string {
  return laravelPortalPath("admin", `/cms-pages/${encodeURIComponent(pageId)}?format=json`);
}

export function seoOverviewPath(): string {
  return laravelPortalPath("admin", "/seo?format=json");
}

export function seoGlobalPath(): string {
  return laravelPortalPath("admin", "/seo/global?format=json");
}

export function seoGlobalPublishPath(): string {
  return laravelPortalPath("admin", "/seo/global/publish?format=json");
}

export function seoSocialPath(): string {
  return laravelPortalPath("admin", "/seo/social?format=json");
}

export function seoVerificationPath(): string {
  return laravelPortalPath("admin", "/seo/verification?format=json");
}

export function seoVerificationPublishPath(): string {
  return laravelPortalPath("admin", "/seo/verification/publish?format=json");
}

export function seoPageEditPath(sourceType: string, sourceId: string): string {
  return laravelPortalPath(
    "admin",
    `/seo/pages/${encodeURIComponent(sourceType)}/${encodeURIComponent(sourceId)}?format=json`,
  );
}

export function seoPagePublishPath(sourceType: string, sourceId: string): string {
  return laravelPortalPath(
    "admin",
    `/seo/pages/${encodeURIComponent(sourceType)}/${encodeURIComponent(sourceId)}/publish?format=json`,
  );
}

export function seoAuditPath(): string {
  return laravelPortalPath("admin", "/seo/audit?format=json");
}

export function loginOtpSettingsPath(): string {
  return laravelPortalPath("admin", "/settings/login-otp?format=json");
}

export function aiAssistantSettingsPath(): string {
  return laravelPortalPath("admin", "/settings/ai-assistant?format=json");
}

export function customerQueriesIndexPath(query = ""): string {
  const suffix = query ? `&${query.replace(/^\?/, "").replace(/^&/, "")}` : "";
  return laravelPortalPath("admin", `/customer-queries?format=json${suffix}`);
}

export function customerQueryShowPath(queryId: string | number): string {
  return laravelPortalPath("admin", `/customer-queries/${encodeURIComponent(String(queryId))}?format=json`);
}

export function customerQueryStatusPath(queryId: string | number): string {
  return laravelPortalPath(
    "admin",
    `/customer-queries/${encodeURIComponent(String(queryId))}/status?format=json`,
  );
}

export function adminUserShowPath(userId: string | number): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(String(userId))}?format=json`);
}

export function adminUserEditPath(userId: string | number): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(String(userId))}/edit?format=json`);
}

export function adminUserUpdatePath(userId: string | number): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(String(userId))}?format=json`);
}

export function staffManagementPath(query = ""): string {
  const suffix = query ? `&${query.replace(/^\?/, "").replace(/^&/, "")}` : "";
  return laravelPortalPath("admin", `/staff?format=json${suffix}`);
}

export function markupsIndexPath(query = ""): string {
  const suffix = query ? `&${query.replace(/^\?/, "").replace(/^&/, "")}` : "";
  return laravelPortalPath("admin", `/markups?format=json${suffix}`);
}

export function markupStorePath(): string {
  return laravelPortalPath("admin", "/markups?format=json");
}

export function markupUpdatePath(ruleId: string | number): string {
  return laravelPortalPath("admin", `/markups/${encodeURIComponent(String(ruleId))}?format=json`);
}

export function markupTogglePath(ruleId: string | number): string {
  return laravelPortalPath(
    "admin",
    `/markups/${encodeURIComponent(String(ruleId))}/toggle-status?format=json`,
  );
}

export function goLiveChecklistPath(): string {
  return laravelPortalPath("admin", "/go-live-checklist?format=json");
}

export function groupTicketingAdminPath(): string {
  return laravelPortalPath("admin", "/group-ticketing?format=json");
}

export function communicationsSettingsPath(): string {
  return laravelPortalPath("admin", "/settings/communications?format=json");
}
