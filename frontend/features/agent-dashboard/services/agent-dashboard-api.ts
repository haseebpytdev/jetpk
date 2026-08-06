import { laravelRequest } from "@/lib/api/laravel-action-client";
import type { ApiResult } from "@/lib/api/types";
import type { BookingConfirmation } from "@/features/standard-booking/types/review-payment";
import type {
  AgentAccountingLedgerOverview,
  AgentAgencyProfile,
  AgentBookingListItem,
  AgentCapabilities,
  AgentCommissionOverview,
  AgentDashboardOverview,
  AgentFinanceStatement,
  AgentInvoice,
  AgentPayment,
  AgentProfile,
  AgentReportsOverview,
  AgentSavedTraveler,
  AgentStaffDetail,
  AgentStaffListResponse,
  AgentSupportCase,
  AgentSupportReply,
  BookingCreateEntry,
  DepositRequest,
  PaginatedMeta,
  WalletLedgerEntry,
  WalletSummary,
} from "../types";

export type AgentApiResult<T> = ApiResult<T>;

function unwrapPayload<T>(result: ApiResult<Record<string, unknown>>): AgentApiResult<T> {
  if (!result.ok) {
    return result;
  }

  const payload = result.data;
  if (payload && typeof payload === "object" && "ok" in payload && payload.ok === false) {
    const message = (payload as { message?: string }).message ?? "Request failed.";
    return {
      ok: false,
      code: ((payload as { code?: string }).code ?? "unknown") as import("@/lib/api/types").ApiErrorCode,
      status: result.status,
      message,
      errors: (payload as { errors?: Record<string, string[]> }).errors,
      data: payload,
    };
  }

  return { ok: true, data: payload as unknown as T, status: result.status };
}

async function agentGet<T>(path: string): Promise<AgentApiResult<T>> {
  const result = await laravelRequest<Record<string, unknown>>(path, {
    method: "GET",
    retryOnNetworkError: true,
  });

  return unwrapPayload<T>(result);
}

async function agentMutation<T>(
  path: string,
  options: {
    method?: "POST" | "PATCH" | "PUT" | "DELETE";
    formData?: FormData;
    json?: unknown;
    retryCsrfOnce?: boolean;
  },
): Promise<AgentApiResult<T>> {
  const result = await laravelRequest<Record<string, unknown>>(path, {
    method: options.method ?? "POST",
    formData: options.formData,
    json: options.json,
    retryCsrfOnce: options.retryCsrfOnce ?? false,
  });

  return unwrapPayload<T>(result);
}

export async function fetchAgentDashboardOverview(): Promise<AgentApiResult<AgentDashboardOverview>> {
  return agentGet<AgentDashboardOverview>("/agent?format=json");
}

export async function fetchAgentCapabilities(): Promise<AgentApiResult<AgentCapabilities>> {
  const result = await fetchAgentDashboardOverview();
  if (!result.ok) return result;
  return { ok: true, data: result.data.capabilities, status: result.status };
}

export async function fetchAgentBookings(params: {
  page?: number;
  filter?: string;
}): Promise<AgentApiResult<{ bookings: AgentBookingListItem[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return agentGet(`/agent/bookings?${search.toString()}`);
}

export async function fetchAgentBookingDetail(reference: string): Promise<AgentApiResult<BookingConfirmation>> {
  return agentGet<BookingConfirmation>(`/agent/bookings/${encodeURIComponent(reference)}?format=json`);
}

export async function requestAgentBookingCancellation(
  bookingReference: string,
  payload: { reason?: string; cancellation_type?: string },
): Promise<
  AgentApiResult<{
    message: string;
    cancellation_request: {
      id: number;
      status: string;
      status_label: string;
      message: string;
    };
  }>
> {
  const formData = new FormData();
  formData.set("cancellation_type", payload.cancellation_type ?? "booking_cancel");
  if (payload.reason) formData.set("reason", payload.reason);

  return agentMutation(`/agent/bookings/${encodeURIComponent(bookingReference)}/cancellations?format=json`, {
    method: "POST",
    formData,
  });
}

export async function fetchAgentBookingCreateEntry(): Promise<AgentApiResult<BookingCreateEntry>> {
  return agentGet<BookingCreateEntry>("/agent/bookings/create?format=json");
}

export async function exitAgentBookingMode(): Promise<AgentApiResult<{ message: string; redirect_url: string }>> {
  return agentGet("/agent/bookings/exit-mode?format=json");
}

export async function fetchAgentWallet(): Promise<
  AgentApiResult<{ summary: WalletSummary; recent_ledger_entries: WalletLedgerEntry[]; capabilities: Record<string, boolean> }>
> {
  return agentGet("/agent/wallet?format=json");
}

export async function fetchAgentLedger(params: {
  page?: number;
  type?: string;
  q?: string;
}): Promise<AgentApiResult<{ entries: WalletLedgerEntry[]; pagination: PaginatedMeta; summary: WalletSummary }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.type) search.set("type", params.type);
  if (params.q) search.set("q", params.q);
  return agentGet(`/agent/ledger?${search.toString()}`);
}

export async function fetchAgentDeposits(page = 1): Promise<
  AgentApiResult<{ deposits: DepositRequest[]; pagination: PaginatedMeta; summary: WalletSummary }>
> {
  return agentGet(`/agent/deposits?format=json&page=${page}`);
}

export async function fetchDepositCreateForm(): Promise<
  AgentApiResult<{ fields: Record<string, unknown>; submit_url: string; summary: WalletSummary }>
> {
  return agentGet("/agent/deposits/create?format=json");
}

export async function submitAgentDeposit(formData: FormData): Promise<AgentApiResult<{ redirect_url: string; message?: string }>> {
  return agentMutation("/agent/deposits?format=json", { method: "POST", formData });
}

export async function fetchAgentPayments(params: {
  page?: number;
  filter?: string;
}): Promise<AgentApiResult<{ payments: AgentPayment[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return agentGet(`/agent/payments?${search.toString()}`);
}

export async function fetchAgentInvoices(page = 1): Promise<AgentApiResult<{ invoices: AgentInvoice[]; pagination: PaginatedMeta }>> {
  return agentGet(`/agent/invoices?format=json&page=${page}`);
}

export async function fetchAgentProfile(): Promise<AgentApiResult<AgentProfile>> {
  return agentGet<AgentProfile>("/agent/profile?format=json");
}

export async function updateAgentPersonalProfile(formData: FormData): Promise<AgentApiResult<{ message: string }>> {
  formData.set("_method", "PATCH");
  return agentMutation("/profile", { method: "POST", formData });
}

export async function updateAgentPassword(payload: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<AgentApiResult<{ message: string }>> {
  const formData = new FormData();
  formData.set("current_password", payload.current_password);
  formData.set("password", payload.password);
  formData.set("password_confirmation", payload.password_confirmation);
  formData.set("_method", "PUT");
  return agentMutation("/password", { method: "POST", formData });
}

export async function fetchAgentSupportCases(page = 1): Promise<
  AgentApiResult<{ tickets: AgentSupportCase[]; pagination: PaginatedMeta }>
> {
  return agentGet(`/agent/support/tickets?format=json&page=${page}`);
}

export async function fetchAgentSupportCreateForm(): Promise<
  AgentApiResult<{
    categories: Array<{ value: string; label: string }>;
    bookings: Array<{ id: number; booking_reference: string; route: string; travel_date?: string | null }>;
    turnstile_required: boolean;
    submit_url: string;
  }>
> {
  return agentGet("/agent/support/tickets/create?format=json");
}

export async function fetchAgentSupportCaseDetail(reference: string): Promise<
  AgentApiResult<{ ticket: AgentSupportCase; conversation: AgentSupportReply[]; reply_url: string }>
> {
  return agentGet(`/agent/support/tickets/${encodeURIComponent(reference)}?format=json`);
}

export async function createAgentSupportTicket(payload: {
  subject: string;
  category: string;
  body: string;
  booking_id?: number | null;
}): Promise<AgentApiResult<{ redirect_url: string }>> {
  const formData = new FormData();
  formData.set("subject", payload.subject);
  formData.set("category", payload.category);
  formData.set("body", payload.body);
  if (payload.booking_id) formData.set("booking_id", String(payload.booking_id));
  return agentMutation("/agent/support/tickets?format=json", { method: "POST", formData });
}

export async function replyAgentSupportTicket(reference: string, body: string): Promise<AgentApiResult<unknown>> {
  const formData = new FormData();
  formData.set("body", body);
  return agentMutation(`/agent/support/tickets/${encodeURIComponent(reference)}/reply?format=json`, {
    method: "POST",
    formData,
  });
}

export async function fetchAgentNotifications(page = 1): Promise<
  AgentApiResult<{
    available: boolean;
    message?: string;
    unread_count: number;
    notifications: Array<Record<string, unknown>>;
    pagination: PaginatedMeta;
  }>
> {
  return agentGet(`/agent/notifications?format=json&page=${page}`);
}

export async function fetchAgentStaffList(): Promise<AgentApiResult<AgentStaffListResponse>> {
  return agentGet("/agent/staff?format=json");
}

export async function fetchAgentStaffCreateForm(): Promise<
  AgentApiResult<{
    permission_labels: Record<string, string>;
    default_permissions: string[];
    submit_url: string;
  }>
> {
  return agentGet("/agent/staff/create?format=json");
}

export async function fetchAgentStaffDetail(staffId: number): Promise<AgentApiResult<AgentStaffDetail>> {
  return agentGet(`/agent/staff/${staffId}/edit?format=json`);
}

export async function createAgentStaff(payload: {
  name: string;
  email: string;
  phone?: string;
  password: string;
  permissions: string[];
}): Promise<AgentApiResult<{ message: string; staff: AgentStaffDetail["staff"]; redirect_url: string }>> {
  const formData = new FormData();
  formData.set("name", payload.name);
  formData.set("email", payload.email);
  formData.set("password", payload.password);
  if (payload.phone) formData.set("phone", payload.phone);
  payload.permissions.forEach((permission) => formData.append("permissions[]", permission));
  return agentMutation("/agent/staff?format=json", { method: "POST", formData });
}

export async function updateAgentStaff(
  staffId: number,
  payload: {
    name: string;
    email: string;
    phone?: string;
    status: string;
    password?: string;
    permissions?: string[];
  },
): Promise<AgentApiResult<{ message: string; staff: AgentStaffDetail["staff"] }>> {
  const formData = new FormData();
  formData.set("_method", "PATCH");
  formData.set("name", payload.name);
  formData.set("email", payload.email);
  formData.set("status", payload.status);
  if (payload.phone) formData.set("phone", payload.phone);
  if (payload.password) formData.set("password", payload.password);
  payload.permissions?.forEach((permission) => formData.append("permissions[]", permission));
  return agentMutation(`/agent/staff/${staffId}?format=json`, { method: "POST", formData });
}

export async function deactivateAgentStaff(staffId: number): Promise<AgentApiResult<{ message: string }>> {
  const formData = new FormData();
  formData.set("_method", "DELETE");
  return agentMutation(`/agent/staff/${staffId}?format=json`, { method: "POST", formData });
}

export async function fetchAgentReports(tab = "overview"): Promise<AgentApiResult<AgentReportsOverview>> {
  return agentGet(`/agent/reports?format=json&tab=${encodeURIComponent(tab)}`);
}

export async function fetchAgentCommissions(): Promise<AgentApiResult<AgentCommissionOverview>> {
  return agentGet("/agent/commissions?format=json");
}

export async function fetchAgentAgency(): Promise<AgentApiResult<AgentAgencyProfile>> {
  return agentGet("/agent/agency?format=json");
}

export async function fetchAgentAgencyEditForm(): Promise<
  AgentApiResult<{
    details: Record<string, unknown>;
    supported_fields: string[];
    can_set_agency_prefix: boolean;
    update_url: string;
  }>
> {
  return agentGet("/agent/agency/edit?format=json");
}

export async function updateAgentAgency(formData: FormData): Promise<AgentApiResult<{ message: string; details: Record<string, unknown> }>> {
  formData.set("_method", "PATCH");
  return agentMutation("/agent/agency?format=json", { method: "POST", formData });
}

export async function fetchAgentTravelers(page = 1): Promise<
  AgentApiResult<{
    travelers: AgentSavedTraveler[];
    pagination: PaginatedMeta;
    countries: Array<{ code: string; name: string }>;
    create_url: string;
  }>
> {
  return agentGet(`/agent/travelers?format=json&page=${page}`);
}

export async function fetchAgentTravelerForm(travelerId?: number): Promise<
  AgentApiResult<{
    traveler: AgentSavedTraveler;
    countries: Array<{ code: string; name: string }>;
    submit_url: string;
    method: "POST" | "PATCH";
  }>
> {
  const path = travelerId
    ? `/agent/travelers/${travelerId}/edit?format=json`
    : "/agent/travelers/create?format=json";
  return agentGet(path);
}

export async function saveAgentTraveler(
  payload: Record<string, string | boolean>,
  options: { travelerId?: number; method?: "POST" | "PATCH" },
): Promise<AgentApiResult<{ redirect_url: string; traveler: AgentSavedTraveler }>> {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    formData.set(key, typeof value === "boolean" ? (value ? "1" : "0") : value);
  });
  if (options.method === "PATCH" && options.travelerId) {
    formData.set("_method", "PATCH");
    return agentMutation(`/agent/travelers/${options.travelerId}?format=json`, {
      method: "POST",
      formData,
      retryCsrfOnce: true,
    });
  }
  return agentMutation("/agent/travelers?format=json", { method: "POST", formData, retryCsrfOnce: true });
}

export async function deleteAgentTraveler(travelerId: number): Promise<AgentApiResult<{ redirect_url: string }>> {
  const formData = new FormData();
  formData.set("_method", "DELETE");
  return agentMutation(`/agent/travelers/${travelerId}?format=json`, {
    method: "POST",
    formData,
    retryCsrfOnce: true,
  });
}

export async function fetchAgentFinanceStatement(params: {
  date_from?: string;
  date_to?: string;
}): Promise<AgentApiResult<AgentFinanceStatement>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.date_from) search.set("date_from", params.date_from);
  if (params.date_to) search.set("date_to", params.date_to);
  return agentGet(`/agent/finance/statement?${search.toString()}`);
}

export async function fetchAgentAccountingLedger(params: {
  page?: number;
  per_page?: number;
  date_from?: string;
  date_to?: string;
  status?: string;
  transaction_type?: string;
  q?: string;
}): Promise<AgentApiResult<AgentAccountingLedgerOverview>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.per_page) search.set("per_page", String(params.per_page));
  if (params.date_from) search.set("date_from", params.date_from);
  if (params.date_to) search.set("date_to", params.date_to);
  if (params.status) search.set("status", params.status);
  if (params.transaction_type) search.set("transaction_type", params.transaction_type);
  if (params.q) search.set("q", params.q);
  return agentGet(`/agent/accounting/ledger?${search.toString()}`);
}

export function agentApiErrorMessage(result: { ok: false; message: string; code?: string; status: number }): string {
  if (result.code === "unauthorized" || result.status === 401) {
    return "Your session has expired. Please sign in again.";
  }
  if (result.code === "forbidden" || result.status === 403) {
    return "You do not have access to this record.";
  }
  if (result.code === "not_found" || result.status === 404) {
    return "This record is unavailable.";
  }
  if (result.code === "conflict" || result.status === 409) {
    return result.message;
  }
  if (result.code === "csrf_expired" || result.status === 419) {
    return result.message || "Your session expired. Please try again.";
  }
  if (result.code === "validation" || result.status === 422) {
    return result.message;
  }
  if (result.code === "rate_limit" || result.status === 429) {
    return result.message;
  }
  if (result.code === "server" || result.status >= 500) {
    return result.message || "Something went wrong. Please try again.";
  }
  return result.message;
}
