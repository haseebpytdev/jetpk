import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import { laravelApiPath } from "@/services/flight-search";
import type { BookingConfirmation } from "@/features/standard-booking/types/review-payment";
import type {
  AgentBookingListItem,
  AgentCapabilities,
  AgentDashboardOverview,
  AgentInvoice,
  AgentPayment,
  AgentProfile,
  AgentSupportCase,
  AgentSupportReply,
  DepositRequest,
  PaginatedMeta,
  WalletLedgerEntry,
  WalletSummary,
} from "../types";

type JsonResult<T> =
  | { ok: true; data: T }
  | { ok: false; status: number; message: string; errors?: LaravelValidationErrors };

async function fetchAgentJson<T>(path: string, init?: RequestInit): Promise<JsonResult<T>> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(init?.headers as Record<string, string> | undefined),
  };

  try {
    const response = await fetch(laravelApiPath(path), {
      ...init,
      credentials: "include",
      headers,
    });

    const body = (await response.json()) as T & { ok?: boolean; message?: string; errors?: LaravelValidationErrors };

    if (!response.ok || body.ok === false) {
      return {
        ok: false,
        status: response.status,
        message: (body as { message?: string }).message ?? "Request failed.",
        errors: (body as { errors?: LaravelValidationErrors }).errors,
      };
    }

    return { ok: true, data: body };
  } catch {
    return { ok: false, status: 0, message: "We could not load your agent dashboard. Please try again." };
  }
}

export async function fetchAgentDashboardOverview(): Promise<JsonResult<AgentDashboardOverview>> {
  return fetchAgentJson<AgentDashboardOverview>("/agent?format=json");
}

export async function fetchAgentCapabilities(): Promise<JsonResult<AgentCapabilities>> {
  const result = await fetchAgentDashboardOverview();
  if (!result.ok) return result;
  return { ok: true, data: result.data.capabilities };
}

export async function fetchAgentBookings(params: {
  page?: number;
  filter?: string;
}): Promise<JsonResult<{ bookings: AgentBookingListItem[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return fetchAgentJson(`/agent/bookings?${search.toString()}`);
}

export async function fetchAgentBookingDetail(reference: string): Promise<JsonResult<BookingConfirmation>> {
  return fetchAgentJson<BookingConfirmation>(`/agent/bookings/${encodeURIComponent(reference)}?format=json`);
}

export async function fetchAgentWallet(): Promise<
  JsonResult<{ summary: WalletSummary; recent_ledger_entries: WalletLedgerEntry[]; capabilities: Record<string, boolean> }>
> {
  return fetchAgentJson("/agent/wallet?format=json");
}

export async function fetchAgentLedger(params: {
  page?: number;
  type?: string;
  q?: string;
}): Promise<JsonResult<{ entries: WalletLedgerEntry[]; pagination: PaginatedMeta; summary: WalletSummary }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.type) search.set("type", params.type);
  if (params.q) search.set("q", params.q);
  return fetchAgentJson(`/agent/ledger?${search.toString()}`);
}

export async function fetchAgentDeposits(page = 1): Promise<
  JsonResult<{ deposits: DepositRequest[]; pagination: PaginatedMeta; summary: WalletSummary }>
> {
  return fetchAgentJson(`/agent/deposits?format=json&page=${page}`);
}

export async function fetchDepositCreateForm(): Promise<
  JsonResult<{ fields: Record<string, unknown>; submit_url: string; summary: WalletSummary }>
> {
  return fetchAgentJson("/agent/deposits/create?format=json");
}

export async function submitAgentDeposit(formData: FormData): Promise<JsonResult<{ redirect_url: string }>> {
  const csrf = await ensureLaravelCsrfToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  return fetchAgentJson("/agent/deposits", {
    method: "POST",
    body: formData,
    headers,
  });
}

export async function fetchAgentPayments(params: {
  page?: number;
  filter?: string;
}): Promise<JsonResult<{ payments: AgentPayment[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return fetchAgentJson(`/agent/payments?${search.toString()}`);
}

export async function fetchAgentInvoices(page = 1): Promise<JsonResult<{ invoices: AgentInvoice[]; pagination: PaginatedMeta }>> {
  return fetchAgentJson(`/agent/invoices?format=json&page=${page}`);
}

export async function fetchAgentProfile(): Promise<JsonResult<AgentProfile>> {
  return fetchAgentJson<AgentProfile>("/agent/profile?format=json");
}

export async function updateAgentPersonalProfile(formData: FormData): Promise<JsonResult<{ message: string }>> {
  const csrf = await ensureLaravelCsrfToken();
  formData.set("_method", "PATCH");
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  return fetchAgentJson("/profile", { method: "POST", body: formData, headers });
}

export async function updateAgentPassword(payload: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<JsonResult<{ message: string }>> {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  formData.set("current_password", payload.current_password);
  formData.set("password", payload.password);
  formData.set("password_confirmation", payload.password_confirmation);
  formData.set("_method", "PUT");

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  return fetchAgentJson("/password", { method: "POST", body: formData, headers });
}

export async function fetchAgentSupportCases(page = 1): Promise<
  JsonResult<{ tickets: AgentSupportCase[]; pagination: PaginatedMeta }>
> {
  return fetchAgentJson(`/agent/support/tickets?format=json&page=${page}`);
}

export async function fetchAgentSupportCreateForm(): Promise<
  JsonResult<{
    categories: Array<{ value: string; label: string }>;
    bookings: Array<{ id: number; booking_reference: string; route: string; travel_date?: string | null }>;
    turnstile_required: boolean;
    submit_url: string;
  }>
> {
  return fetchAgentJson("/agent/support/tickets/create?format=json");
}

export async function fetchAgentSupportCaseDetail(reference: string): Promise<
  JsonResult<{ ticket: AgentSupportCase; conversation: AgentSupportReply[]; reply_url: string }>
> {
  return fetchAgentJson(`/agent/support/tickets/${encodeURIComponent(reference)}?format=json`);
}

export async function createAgentSupportTicket(payload: {
  subject: string;
  category: string;
  body: string;
  booking_id?: number | null;
}): Promise<JsonResult<{ redirect_url: string }>> {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  formData.set("subject", payload.subject);
  formData.set("category", payload.category);
  formData.set("body", payload.body);
  if (payload.booking_id) formData.set("booking_id", String(payload.booking_id));

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  return fetchAgentJson("/agent/support/tickets", { method: "POST", body: formData, headers });
}

export async function replyAgentSupportTicket(reference: string, body: string): Promise<JsonResult<unknown>> {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  formData.set("body", body);

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  return fetchAgentJson(`/agent/support/tickets/${encodeURIComponent(reference)}/reply`, {
    method: "POST",
    body: formData,
    headers,
  });
}

export async function fetchAgentNotifications(page = 1): Promise<
  JsonResult<{
    available: boolean;
    message?: string;
    unread_count: number;
    notifications: Array<Record<string, unknown>>;
    pagination: PaginatedMeta;
  }>
> {
  return fetchAgentJson(`/agent/notifications?format=json&page=${page}`);
}

export async function fetchAgentNotificationUnreadSummary(): Promise<JsonResult<{ available: boolean; unread_count: number }>> {
  return fetchAgentJson("/agent/notifications/unread-summary?format=json");
}
