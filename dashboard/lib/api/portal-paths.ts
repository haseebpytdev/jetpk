import { getLaravelApiBase } from "@/lib/read-only/laravel/api-base";
import type { DashboardPortal } from "@/lib/portal-path";

/**
 * Normalize Next public IDs (JP-USR-0001, AG-00012) to Laravel route keys.
 */
export function laravelModelKey(id: string): string {
  const trimmed = id.trim();
  const prefixed = /^(?:JP-USR|JP-BKG|JP-PMT|JP-TKT|JP-PNR|JP-CMS|AG)-0*(\d+)$/i.exec(trimmed);
  if (prefixed) {
    return prefixed[1];
  }
  return trimmed;
}

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

export function supplierBookingCreatePath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/supplier-booking?format=json`);
}

export function bookingStatusUpdatePath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/status?format=json`);
}

export function prepareSupplierPnrContextPath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(
    portal,
    `/bookings/${encodeURIComponent(bookingId)}/prepare-supplier-pnr-context?format=json`,
  );
}

export function syncPnrItineraryPath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/sync-pnr-itinerary?format=json`);
}

export function bookingAuditExportPath(portal: DashboardPortal, bookingId: string): string {
  return laravelPortalPath(portal, `/bookings/${encodeURIComponent(bookingId)}/audit/export`);
}

export function reconciliationExportPath(): string {
  return laravelPortalPath("admin", `/accounting/reconciliation/export`);
}

export function financeDashboardExportPath(): string {
  return laravelPortalPath("admin", `/finance/dashboard/export`);
}

export function adminDirectCancelPath(bookingId: string): string {
  return laravelPortalPath("admin", `/bookings/${encodeURIComponent(bookingId)}/admin-direct-cancel?format=json`);
}

export function userActivatePath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(laravelModelKey(userId))}/activate?format=json`);
}

export function userSuspendPath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(laravelModelKey(userId))}/suspend?format=json`);
}

export function agencyUserRolePath(agencyId: string, userId: string): string {
  return laravelPortalPath(
    "admin",
    `/agencies/${encodeURIComponent(laravelModelKey(agencyId))}/users/${encodeURIComponent(laravelModelKey(userId))}/agency-role?format=json`,
  );
}

export function agencyUserPermissionsPath(agencyId: string, userId: string): string {
  return laravelPortalPath(
    "admin",
    `/agencies/${encodeURIComponent(laravelModelKey(agencyId))}/users/${encodeURIComponent(laravelModelKey(userId))}/agent-permissions?format=json`,
  );
}

export function agencyUserPermissionsApplyTemplatePath(agencyId: string, userId: string): string {
  return laravelPortalPath(
    "admin",
    `/agencies/${encodeURIComponent(laravelModelKey(agencyId))}/users/${encodeURIComponent(laravelModelKey(userId))}/agent-permissions/apply-template?format=json`,
  );
}

export function agencyPrefixPath(agencyId: string): string {
  return laravelPortalPath("admin", `/agencies/${encodeURIComponent(laravelModelKey(agencyId))}/prefix?format=json`);
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
  return laravelPortalPath("admin", `/commissions/entries/${encodeURIComponent(laravelModelKey(entryId))}/approve?format=json`);
}

export function commissionEntryRejectPath(entryId: string): string {
  return laravelPortalPath("admin", `/commissions/entries/${encodeURIComponent(laravelModelKey(entryId))}/reject?format=json`);
}

export function commissionAdjustmentPath(agentId: string): string {
  return laravelPortalPath("admin", `/commissions/${encodeURIComponent(laravelModelKey(agentId))}/adjustments?format=json`);
}

export function commissionPayoutPath(agentId: string): string {
  return laravelPortalPath("admin", `/commissions/${encodeURIComponent(laravelModelKey(agentId))}/payouts?format=json`);
}

export function commissionStatementPath(agentId: string): string {
  return laravelPortalPath("admin", `/commissions/${encodeURIComponent(laravelModelKey(agentId))}/statements?format=json`);
}

export function deliveryLogResendPath(communicationLogId: string): string {
  return laravelPortalPath(
    "admin",
    `/settings/communications/delivery-log/${encodeURIComponent(laravelModelKey(communicationLogId))}/resend?format=json`,
  );
}

export function reportsExportPath(type: string, query: Record<string, string | undefined> = {}): string {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  const qs = params.toString();
  return laravelPortalPath(
    "admin",
    `/reports/export/${encodeURIComponent(type)}${qs ? `?${qs}` : ""}`,
  );
}

export function pageSettingsRefreshHomeFaresPath(): string {
  return laravelPortalPath("admin", "/page-settings/home/refresh-fares?format=json");
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

export function financeAdjustmentIndexPath(): string {
  return laravelPortalPath("admin", "/finance/adjustments?format=json");
}

export function financeAdjustmentCreatePath(agencyId?: string): string {
  const query = agencyId ? `&agency_id=${encodeURIComponent(agencyId)}` : "";
  return laravelPortalPath("admin", `/finance/adjustments/create?format=json${query}`);
}

export function financeAdjustmentReversePath(walletTransactionId: string): string {
  return laravelPortalPath(
    "admin",
    `/finance/adjustments/${encodeURIComponent(walletTransactionId)}/reverse?format=json`,
  );
}

export function communicationsSettingsPath(): string {
  return laravelPortalPath("admin", "/settings/communications?format=json");
}

export function communicationsTestEmailPath(): string {
  return laravelPortalPath("admin", "/settings/communications/test-email?format=json");
}

export function communicationsTestWhatsappPath(): string {
  return laravelPortalPath("admin", "/settings/communications/test-whatsapp?format=json");
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

export function brandingFooterUpdatePath(): string {
  return laravelPortalPath("admin", "/settings/branding/footer?format=json");
}

export function brandingAboutUpdatePath(): string {
  return laravelPortalPath("admin", "/settings/branding/about-us?format=json");
}

export function messageTemplatesIndexPath(query: Record<string, string | undefined> = {}): string {
  const params = new URLSearchParams({ format: "json" });
  Object.entries(query).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  return laravelPortalPath("admin", `/settings/communications/templates?${params.toString()}`);
}

export function messageTemplateUpdatePath(event: string, channel: string): string {
  return laravelPortalPath(
    "admin",
    `/settings/communications/templates/${encodeURIComponent(event)}/${encodeURIComponent(channel)}?format=json`,
  );
}

export function promoCodesIndexPath(query: Record<string, string | undefined> = {}): string {
  const params = new URLSearchParams({ format: "json" });
  Object.entries(query).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  return laravelPortalPath("admin", `/promo-codes?${params.toString()}`);
}

export function promoCodesStorePath(): string {
  return laravelPortalPath("admin", "/promo-codes?format=json");
}

export function promoCodeUpdatePath(promoId: string): string {
  return laravelPortalPath("admin", `/promo-codes/${encodeURIComponent(laravelModelKey(promoId))}?format=json`);
}

export function promoCodeTogglePath(promoId: string): string {
  return laravelPortalPath(
    "admin",
    `/promo-codes/${encodeURIComponent(laravelModelKey(promoId))}/toggle-status?format=json`,
  );
}

export function financeStatementsIndexPath(): string {
  return laravelPortalPath("admin", "/finance/statements?format=json");
}

export function financeStatementShowPath(
  agencyId: string,
  query: Record<string, string | undefined> = {},
): string {
  const params = new URLSearchParams({ format: "json" });
  Object.entries(query).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  return laravelPortalPath(
    "admin",
    `/finance/statements/${encodeURIComponent(laravelModelKey(agencyId))}?${params.toString()}`,
  );
}

export function financeStatementExportPath(
  agencyId: string,
  query: Record<string, string | undefined> = {},
): string {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  const qs = params.toString();
  return laravelPortalPath(
    "admin",
    `/finance/statements/${encodeURIComponent(laravelModelKey(agencyId))}/export${qs ? `?${qs}` : ""}`,
  );
}

export function bookingDocumentGeneratePath(
  portal: DashboardPortal,
  bookingId: string,
  kind: "confirmation" | "invoice" | "ticket-itinerary" | "refund-note" | "cancellation-confirmation",
): string {
  return laravelPortalPath(
    portal,
    `/bookings/${encodeURIComponent(laravelModelKey(bookingId))}/documents/${kind}?format=json`,
  );
}

export function bookingPaymentReceiptPath(portal: DashboardPortal, paymentId: string): string {
  return laravelPortalPath(
    portal,
    `/bookings/payments/${encodeURIComponent(laravelModelKey(paymentId))}/documents/receipt?format=json`,
  );
}

export function bookingDocumentDownloadPath(portal: DashboardPortal, documentId: string): string {
  return laravelPortalPath(portal, `/bookings/documents/${encodeURIComponent(laravelModelKey(documentId))}/download`);
}

export function usersStorePath(): string {
  return laravelPortalPath("admin", "/users?format=json");
}

export function userInvitePath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(laravelModelKey(userId))}/send-invite?format=json`);
}

export function userResetPasswordPath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(laravelModelKey(userId))}/reset-password-link?format=json`);
}

export function userUpdatePath(userId: string): string {
  return laravelPortalPath("admin", `/users/${encodeURIComponent(laravelModelKey(userId))}?format=json`);
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

export function pageSettingsUnpublishPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}/unpublish?format=json`);
}

export function pageSettingsDuplicatePath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}/duplicate?format=json`);
}

export function pageSettingsAssetsPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}/assets?format=json`);
}

export function pageSettingsAttachAssetPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}/assets/attach?format=json`);
}

export function pageSettingsPreviewPath(pageKey: string): string {
  return laravelPortalPath("admin", `/page-settings/${encodeURIComponent(pageKey)}/preview?format=json`);
}

export function pageSettingsIndexPath(): string {
  return laravelPortalPath("admin", `/page-settings/catalog?format=json`);
}
