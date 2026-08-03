import type { DashboardPortal } from "@/lib/portal-path";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  depositApprovePath,
  depositRejectPath,
  paymentRejectPath,
  paymentVerifyPath,
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
