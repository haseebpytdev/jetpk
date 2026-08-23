import type { DashboardPortal } from "@/lib/portal-path";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  agencyPrefixPath,
  agencyUserPermissionsApplyTemplatePath,
  agencyUserPermissionsPath,
  agencyUserRolePath,
  agentApplicationApprovePath,
  agentApplicationNeedsMoreInfoPath,
  agentApplicationRejectPath,
  apiSettingsIndexPath,
  apiSettingsStorePath,
  apiSettingsTestPath,
  apiSettingsTogglePath,
  apiSettingsUpdatePath,
  integrationsIndexPath,
  integrationShowPath,
  integrationUpdatePath,
  integrationActivatePath,
  integrationDeactivatePath,
  integrationTestConnectionPath,
  integrationTestPaymentPath,
  brandingSettingsPath,
  bookingAssignStaffPath,
  bookingContactPath,
  bookingNotesPath,
  cancellationApprovePath,
  cancellationProcessPath,
  cancellationRejectPath,
  cancellationStorePath,
  cmsPageArchivePath,
  cmsPageDestroyPath,
  cmsPageDuplicatePath,
  cmsPagePreviewDraftPath,
  cmsPageStorePath,
  cmsPageUpdatePath,
  commissionEntryApprovePath,
  commissionEntryRejectPath,
  depositApprovePath,
  depositRejectPath,
  financeAdjustmentReversePath,
  financeAdjustmentStorePath,
  groupBookingRejectPaymentPath,
  groupBookingVerifyPaymentPath,
  issueTicketPath,
  markupDestroyPath,
  markupStorePath,
  markupTogglePath,
  markupUpdatePath,
  mediaLibraryDestroyPath,
  mediaLibraryIndexPath,
  mediaLibraryStorePath,
  mediaLibraryUpdatePath,
  notificationEventsUpdatePath,
  pageSettingsEditPath,
  pageSettingsPublishPath,
  pageSettingsUnpublishPath,
  pageSettingsAssetsPath,
  pageSettingsAttachAssetPath,
  pageSettingsPreviewPath,
  pageSettingsIndexPath,
  pageSettingsDuplicatePath,
  paymentRejectPath,
  paymentStorePath,
  paymentVerifyPath,
  refundApprovePath,
  refundMarkPaidPath,
  refundRejectPath,
  refundStorePath,
  supportTicketAssignPath,
  supportTicketForwardPath,
  supportTicketReplyPath,
  supportTicketStatusPath,
  userActivatePath,
  userInvitePath,
  userResetPasswordPath,
  userSuspendPath,
  userUpdatePath,
  usersStorePath,
} from "@/lib/api/portal-paths";

type MutationResponse<T> = {
  ok: boolean;
  message?: string;
  code?: string;
  status?: number;
} & T;

/**
 * laravelRequest returns ApiResult `{ ok, data, status }`.
 * Domain callers expect a flattened payload (`hub`, `integration`, `previewUrl`, …).
 */
async function unwrapLaravelData<T extends Record<string, unknown>>(
  result: Awaited<ReturnType<typeof laravelRequest<T>>>,
): Promise<MutationResponse<T>> {
  if (!result.ok) {
    return {
      ok: false,
      message: result.message,
      code: result.code,
      status: result.status,
    } as MutationResponse<T>;
  }

  const payload = (result.data ?? {}) as T;
  const payloadMessage = (payload as Record<string, unknown>).message;

  return {
    ...payload,
    ok: true,
    status: result.status,
    message: typeof payloadMessage === "string" ? payloadMessage : undefined,
  };
}

export async function verifyPaymentReview(
  portal: DashboardPortal,
  paymentId: string,
): Promise<MutationResponse<{ payment?: Record<string, unknown> }>> {
  return laravelRequest(paymentVerifyPath(portal, paymentId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function rejectPaymentReview(
  portal: DashboardPortal,
  paymentId: string,
  reason: string,
): Promise<MutationResponse<{ payment?: Record<string, unknown> }>> {
  return laravelRequest(paymentRejectPath(portal, paymentId), {
    method: "PATCH",
    json: { reason },
    retryCsrfOnce: false,
  });
}

export async function approveDepositReview(
  depositId: string,
): Promise<MutationResponse<{ deposit?: Record<string, unknown> }>> {
  return laravelRequest(depositApprovePath(depositId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function rejectDepositReview(
  depositId: string,
  adminNote: string,
): Promise<MutationResponse<{ deposit?: Record<string, unknown> }>> {
  return laravelRequest(depositRejectPath(depositId), {
    method: "PATCH",
    json: { admin_note: adminNote },
    retryCsrfOnce: false,
  });
}

export async function processCancellationExecution(
  portal: DashboardPortal,
  cancellationRequestId: string,
): Promise<MutationResponse<{ cancellation_request?: Record<string, unknown>; booking?: Record<string, unknown> }>> {
  return laravelRequest(cancellationProcessPath(portal, cancellationRequestId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function markRefundPaidExecution(
  portal: DashboardPortal,
  refundId: string,
  payload?: { reference?: string; notes?: string },
): Promise<MutationResponse<{ refund?: Record<string, unknown>; booking?: Record<string, unknown> }>> {
  return laravelRequest(refundMarkPaidPath(portal, refundId), {
    method: "PATCH",
    json: payload ?? {},
    retryCsrfOnce: false,
  });
}

export async function issueTicketExecution(
  portal: DashboardPortal,
  bookingId: string,
): Promise<MutationResponse<{ booking?: Record<string, unknown> }>> {
  return laravelRequest(issueTicketPath(portal, bookingId), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function approveCancellationReview(
  portal: DashboardPortal,
  cancellationRequestId: string,
): Promise<MutationResponse<{ cancellation_request?: Record<string, unknown>; capabilities?: Record<string, unknown> }>> {
  return laravelRequest(cancellationApprovePath(portal, cancellationRequestId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function rejectCancellationReview(
  portal: DashboardPortal,
  cancellationRequestId: string,
  reason: string,
): Promise<MutationResponse<{ cancellation_request?: Record<string, unknown>; capabilities?: Record<string, unknown> }>> {
  return laravelRequest(cancellationRejectPath(portal, cancellationRequestId), {
    method: "PATCH",
    json: { reason },
    retryCsrfOnce: false,
  });
}

export async function approveRefundReview(
  portal: DashboardPortal,
  refundId: string,
): Promise<MutationResponse<{ refund?: Record<string, unknown>; capabilities?: Record<string, unknown> }>> {
  return laravelRequest(refundApprovePath(portal, refundId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function rejectRefundReview(
  portal: DashboardPortal,
  refundId: string,
  reason: string,
): Promise<MutationResponse<{ refund?: Record<string, unknown>; capabilities?: Record<string, unknown> }>> {
  return laravelRequest(refundRejectPath(portal, refundId), {
    method: "PATCH",
    json: { reason },
    retryCsrfOnce: false,
  });
}

export async function storeBookingNote(
  portal: DashboardPortal,
  bookingId: string,
  note: string,
  isCustomerVisible = false,
): Promise<MutationResponse<{ booking?: Record<string, unknown> }>> {
  return laravelRequest(bookingNotesPath(portal, bookingId), {
    method: "POST",
    json: { note, is_customer_visible: isCustomerVisible },
    retryCsrfOnce: false,
  });
}

export async function updateBookingLocalContact(
  portal: DashboardPortal,
  bookingId: string,
  payload: { email: string; phone: string; country?: string },
): Promise<
  MutationResponse<{
    localContact?: { email: string; phone: string; country: string };
    localAmendment?: Record<string, unknown>;
    policyNote?: string;
  }>
> {
  return laravelRequest(bookingContactPath(portal, bookingId), {
    method: "PATCH",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function createCmsPage(
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ page?: Record<string, unknown> }>> {
  return laravelRequest(cmsPageStorePath(), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function updateCmsPage(
  pageId: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ page?: Record<string, unknown> }>> {
  return laravelRequest(cmsPageUpdatePath(pageId), {
    method: "PATCH",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function previewCmsDraft(
  pageId: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ previewUrl?: string }>> {
  return laravelRequest(cmsPagePreviewDraftPath(pageId), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function archiveCmsPage(
  pageId: string,
): Promise<MutationResponse<{ page?: Record<string, unknown> }>> {
  return laravelRequest(cmsPageArchivePath(pageId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function duplicateCmsPage(
  pageId: string,
): Promise<MutationResponse<{ page?: Record<string, unknown> }>> {
  return laravelRequest(cmsPageDuplicatePath(pageId), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function destroyCmsPage(pageId: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(cmsPageDestroyPath(pageId), {
    method: "DELETE",
    retryCsrfOnce: false,
  });
}

export async function assignBookingStaff(
  bookingId: string,
  staffUserId: number | null,
): Promise<MutationResponse<{ booking?: Record<string, unknown> }>> {
  return laravelRequest(bookingAssignStaffPath(bookingId), {
    method: "PATCH",
    json: { staff_user_id: staffUserId },
    retryCsrfOnce: false,
  });
}

export async function storeCancellationRequest(
  portal: DashboardPortal,
  bookingId: string,
  payload: { reason?: string; cancellation_type: string },
): Promise<MutationResponse<{ cancellation_request?: Record<string, unknown> }>> {
  return laravelRequest(cancellationStorePath(portal, bookingId), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function storeRefundRequest(
  portal: DashboardPortal,
  bookingId: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ refund?: Record<string, unknown> }>> {
  return laravelRequest(refundStorePath(portal, bookingId), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function storeBookingPayment(
  portal: DashboardPortal,
  bookingId: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ payment?: Record<string, unknown> }>> {
  return laravelRequest(paymentStorePath(portal, bookingId), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function activateUser(userId: string): Promise<MutationResponse<{ user?: Record<string, unknown> }>> {
  return laravelRequest(userActivatePath(userId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function suspendUser(userId: string): Promise<MutationResponse<{ user?: Record<string, unknown> }>> {
  return laravelRequest(userSuspendPath(userId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function updateAgencyUserRole(
  agencyId: string,
  userId: string,
  agencyRole: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(agencyUserRolePath(agencyId, userId), {
    method: "PATCH",
    json: { agency_role: agencyRole },
    retryCsrfOnce: false,
  });
}

export async function updateAgencyUserPermissions(
  agencyId: string,
  userId: string,
  permissions: string[],
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(agencyUserPermissionsPath(agencyId, userId), {
    method: "PATCH",
    json: { permissions },
    retryCsrfOnce: false,
  });
}

export async function applyAgencyUserPermissionTemplate(
  agencyId: string,
  userId: string,
  templateKey: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(agencyUserPermissionsApplyTemplatePath(agencyId, userId), {
    method: "POST",
    json: { template_key: templateKey },
    retryCsrfOnce: false,
  });
}

export async function updateAgencyPrefix(
  agencyId: string,
  prefix: string,
): Promise<MutationResponse<{ agency?: Record<string, unknown> }>> {
  return laravelRequest(agencyPrefixPath(agencyId), {
    method: "PATCH",
    json: { prefix },
    retryCsrfOnce: false,
  });
}

export async function approveAgentApplication(
  applicationId: string,
  note = "",
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(agentApplicationApprovePath(applicationId), {
    method: "PATCH",
    json: { internal_note: note },
    retryCsrfOnce: false,
  });
}

export async function rejectAgentApplication(
  applicationId: string,
  reason: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(agentApplicationRejectPath(applicationId), {
    method: "PATCH",
    json: { internal_note: reason, reason },
    retryCsrfOnce: false,
  });
}

export async function agentApplicationNeedsMoreInfo(
  applicationId: string,
  note: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(agentApplicationNeedsMoreInfoPath(applicationId), {
    method: "PATCH",
    json: { internal_note: note, note },
    retryCsrfOnce: false,
  });
}

export async function assignSupportTicket(
  ticketId: string,
  assignedToUserId: number | null,
): Promise<MutationResponse<{ ticket?: Record<string, unknown> }>> {
  return laravelRequest(supportTicketAssignPath(ticketId), {
    method: "PATCH",
    json: { assigned_to_user_id: assignedToUserId },
    retryCsrfOnce: false,
  });
}

export async function forwardSupportTicket(
  ticketId: string,
  forwardedToAgentId: number | null,
): Promise<MutationResponse<{ ticket?: Record<string, unknown> }>> {
  return laravelRequest(supportTicketForwardPath(ticketId), {
    method: "PATCH",
    json: { forwarded_to_agent_id: forwardedToAgentId },
    retryCsrfOnce: false,
  });
}

export async function replySupportTicket(
  portal: DashboardPortal,
  ticketId: string,
  body: string,
  visibility: "internal" | "customer_visible",
): Promise<MutationResponse<{ ticket?: Record<string, unknown> }>> {
  return laravelRequest(supportTicketReplyPath(portal, ticketId), {
    method: "POST",
    json: { body, visibility },
    retryCsrfOnce: false,
  });
}

export async function updateSupportTicketStatus(
  portal: DashboardPortal,
  ticketId: string,
  status: string,
): Promise<MutationResponse<{ ticket?: Record<string, unknown> }>> {
  return laravelRequest(supportTicketStatusPath(portal, ticketId), {
    method: "PATCH",
    json: { status },
    retryCsrfOnce: false,
  });
}

export async function approveCommissionEntry(
  entryId: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(commissionEntryApprovePath(entryId), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function rejectCommissionEntry(
  entryId: string,
  reason: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(commissionEntryRejectPath(entryId), {
    method: "POST",
    json: { reason },
    retryCsrfOnce: false,
  });
}

export async function verifyGroupBookingPayment(
  groupBookingId: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(groupBookingVerifyPaymentPath(groupBookingId), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function rejectGroupBookingPayment(
  groupBookingId: string,
  reason: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(groupBookingRejectPaymentPath(groupBookingId), {
    method: "POST",
    json: { reason },
    retryCsrfOnce: false,
  });
}

export async function storeFinanceAdjustment(
  payload: Record<string, unknown>,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(financeAdjustmentStorePath(), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function reverseFinanceAdjustment(
  walletTransactionId: string,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(financeAdjustmentReversePath(walletTransactionId), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function createMarkupRule(payload: Record<string, unknown>): Promise<MutationResponse<{ markup?: Record<string, unknown> }>> {
  return laravelRequest(markupStorePath(), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function updateMarkupRule(
  markupId: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ markup?: Record<string, unknown> }>> {
  return laravelRequest(markupUpdatePath(markupId), {
    method: "PATCH",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function toggleMarkupRule(markupId: string): Promise<MutationResponse<{ markup?: Record<string, unknown> }>> {
  return laravelRequest(markupTogglePath(markupId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function deleteMarkupRule(markupId: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(markupDestroyPath(markupId), {
    method: "DELETE",
    retryCsrfOnce: false,
  });
}

export async function listApiConnections(): Promise<MutationResponse<{ connections?: Record<string, unknown>[] }>> {
  return laravelRequest(apiSettingsIndexPath(), {
    method: "GET",
    retryCsrfOnce: false,
  });
}

export async function createApiConnection(payload: Record<string, unknown>): Promise<MutationResponse<{ connection?: Record<string, unknown> }>> {
  return laravelRequest(apiSettingsStorePath(), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function updateApiConnection(
  connectionId: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ connection?: Record<string, unknown> }>> {
  return laravelRequest(apiSettingsUpdatePath(connectionId), {
    method: "PATCH",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function toggleApiConnection(connectionId: string): Promise<MutationResponse<{ connection?: Record<string, unknown> }>> {
  return laravelRequest(apiSettingsTogglePath(connectionId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function testApiConnection(connectionId: string): Promise<MutationResponse<{ test?: Record<string, unknown> }>> {
  return laravelRequest(apiSettingsTestPath(connectionId), {
    method: "PATCH",
    retryCsrfOnce: false,
  });
}

export async function listIntegrations(category?: string): Promise<MutationResponse<{ hub?: Record<string, unknown>; permissions?: Record<string, boolean> }>> {
  return unwrapLaravelData(
    await laravelRequest<{ hub?: Record<string, unknown>; permissions?: Record<string, boolean> }>(
      integrationsIndexPath(category),
      {
        method: "GET",
        retryCsrfOnce: false,
      },
    ),
  );
}

export async function showIntegration(code: string): Promise<MutationResponse<{ integration?: Record<string, unknown> }>> {
  return unwrapLaravelData(
    await laravelRequest<{ integration?: Record<string, unknown> }>(integrationShowPath(code), {
      method: "GET",
      retryCsrfOnce: false,
    }),
  );
}

export async function updateIntegration(
  code: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ configuration?: Record<string, unknown> }>> {
  return unwrapLaravelData(
    await laravelRequest<{ configuration?: Record<string, unknown> }>(integrationUpdatePath(code), {
      method: "PATCH",
      json: payload,
      retryCsrfOnce: false,
    }),
  );
}

export async function activateIntegration(code: string): Promise<MutationResponse<Record<string, unknown>>> {
  return unwrapLaravelData(
    await laravelRequest<Record<string, unknown>>(integrationActivatePath(code), {
      method: "POST",
      json: {},
      retryCsrfOnce: false,
    }),
  );
}

export async function deactivateIntegration(code: string): Promise<MutationResponse<Record<string, unknown>>> {
  return unwrapLaravelData(
    await laravelRequest<Record<string, unknown>>(integrationDeactivatePath(code), {
      method: "POST",
      json: {},
      retryCsrfOnce: false,
    }),
  );
}

export async function testIntegrationConnection(code: string): Promise<MutationResponse<{ result?: Record<string, unknown> }>> {
  return unwrapLaravelData(
    await laravelRequest<{ result?: Record<string, unknown> }>(integrationTestConnectionPath(code), {
      method: "POST",
      json: {},
      retryCsrfOnce: false,
    }),
  );
}

export async function testIntegrationPayment(
  code: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ result?: Record<string, unknown> }>> {
  return unwrapLaravelData(
    await laravelRequest<{ result?: Record<string, unknown> }>(integrationTestPaymentPath(code), {
      method: "POST",
      json: payload,
      retryCsrfOnce: false,
    }),
  );
}

export async function updateNotificationCategories(
  categories: Array<Record<string, unknown>>,
): Promise<MutationResponse<{ notifications?: Record<string, unknown> }>> {
  return laravelRequest(notificationEventsUpdatePath(), {
    method: "PATCH",
    json: { categories },
    retryCsrfOnce: false,
  });
}

export async function loadOrganizationProfile(): Promise<MutationResponse<{ organization?: Record<string, unknown> }>> {
  return laravelRequest(brandingSettingsPath(), {
    method: "GET",
    retryCsrfOnce: false,
  });
}

export async function updateOrganizationProfile(payload: Record<string, unknown>): Promise<MutationResponse<{ organization?: Record<string, unknown> }>> {
  return laravelRequest(brandingSettingsPath(), {
    method: "PATCH",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function createPlatformUser(payload: Record<string, unknown>): Promise<MutationResponse<{ user?: Record<string, unknown> }>> {
  return laravelRequest(usersStorePath(), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function sendUserInvite(userId: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(userInvitePath(userId), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function sendUserPasswordReset(userId: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(userResetPasswordPath(userId), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function updatePlatformUser(
  userId: string,
  payload: Record<string, unknown>,
): Promise<MutationResponse<{ user?: Record<string, unknown> }>> {
  return laravelRequest(userUpdatePath(userId), {
    method: "PATCH",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function loadMediaLibrary(): Promise<MutationResponse<{ media?: Record<string, unknown>[] }>> {
  return laravelRequest(mediaLibraryIndexPath(), {
    method: "GET",
    retryCsrfOnce: false,
  });
}

export async function uploadMediaLibraryFile(formData: FormData): Promise<MutationResponse<{ media?: Record<string, unknown> }>> {
  return laravelRequest(mediaLibraryStorePath(), {
    method: "POST",
    formData,
    retryCsrfOnce: false,
  });
}

export async function updateMediaLibraryItem(
  mediaId: string,
  payload: { alt_text?: string },
): Promise<MutationResponse<{ media?: Record<string, unknown> }>> {
  return laravelRequest(mediaLibraryUpdatePath(mediaId), {
    method: "PATCH",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function deleteMediaLibraryItem(mediaId: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(mediaLibraryDestroyPath(mediaId), {
    method: "DELETE",
    retryCsrfOnce: false,
  });
}

export async function loadPageSettings(pageKey: string): Promise<MutationResponse<{ content?: Record<string, unknown>; pageKey?: string }>> {
  return laravelRequest(pageSettingsEditPath(pageKey), {
    method: "GET",
    retryCsrfOnce: false,
  });
}

export async function savePageSettings(
  pageKey: string,
  content: Record<string, unknown>,
): Promise<MutationResponse<{ content?: Record<string, unknown> }>> {
  return laravelRequest(pageSettingsEditPath(pageKey), {
    method: "PATCH",
    json: { content },
    retryCsrfOnce: false,
  });
}

export async function publishPageSettings(pageKey: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(pageSettingsPublishPath(pageKey), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function uploadPageSettingsAsset(
  pageKey: string,
  formData: FormData,
): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(pageSettingsAssetsPath(pageKey), {
    method: "POST",
    formData,
    retryCsrfOnce: false,
  });
}

export async function attachPageSettingsAsset(
  pageKey: string,
  payload: { asset_key: string; agency_media_id: number | string; alt_text?: string },
): Promise<MutationResponse<{ asset?: Record<string, unknown> }>> {
  return laravelRequest(pageSettingsAttachAssetPath(pageKey), {
    method: "POST",
    json: payload,
    retryCsrfOnce: false,
  });
}

export async function beginPageSettingsPreview(pageKey: string): Promise<MutationResponse<Record<string, unknown>>> {
  return unwrapLaravelData(
    await laravelRequest<Record<string, unknown>>(pageSettingsPreviewPath(pageKey), {
      method: "POST",
      retryCsrfOnce: false,
    }),
  );
}

export async function unpublishPageSettings(pageKey: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(pageSettingsUnpublishPath(pageKey), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function duplicatePageSettings(pageKey: string): Promise<MutationResponse<Record<string, unknown>>> {
  return laravelRequest(pageSettingsDuplicatePath(pageKey), {
    method: "POST",
    retryCsrfOnce: false,
  });
}

export async function loadPageSettingsIndex(): Promise<MutationResponse<{ pages?: Array<Record<string, unknown>> }>> {
  return laravelRequest(pageSettingsIndexPath(), {
    method: "GET",
    retryCsrfOnce: false,
  });
}

