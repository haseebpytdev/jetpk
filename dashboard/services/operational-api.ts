import type { DashboardPortal } from "@/lib/portal-path";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  cancellationProcessPath,
  depositApprovePath,
  depositRejectPath,
  issueTicketPath,
  paymentRejectPath,
  paymentVerifyPath,
  refundMarkPaidPath,
} from "@/lib/api/portal-paths";

type MutationResponse<T> = {
  ok: boolean;
  message?: string;
  code?: string;
} & T;

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
