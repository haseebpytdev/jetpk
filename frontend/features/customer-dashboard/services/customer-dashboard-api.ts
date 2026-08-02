import { laravelRequest } from "@/lib/api/laravel-action-client";
import type { ApiResult } from "@/lib/api/types";
import type {
  CustomerDashboardOverview,
  CustomerInvoice,
  CustomerPayment,
  CustomerProfile,
  CustomerSavedTraveler,
  CustomerSupportCase,
  CustomerSupportReply,
  PaginatedMeta,
} from "../types";
import type { BookingConfirmation } from "@/features/standard-booking/types/review-payment";
import type { CustomerBookingListItem } from "../types";

export type CustomerApiResult<T> = ApiResult<T>;

function unwrapPayload<T>(result: ApiResult<Record<string, unknown>>): CustomerApiResult<T> {
  if (!result.ok) {
    return result;
  }

  const payload = result.data;
  if (payload && typeof payload === "object" && "ok" in payload && payload.ok === false) {
    const message = (payload as { message?: string }).message ?? "Request failed.";
    return {
      ok: false,
      code: "unknown",
      status: result.status,
      message,
      errors: (payload as { errors?: Record<string, string[]> }).errors,
      data: payload,
    };
  }

  return { ok: true, data: payload as unknown as T, status: result.status };
}

async function customerGet<T>(path: string): Promise<CustomerApiResult<T>> {
  const result = await laravelRequest<Record<string, unknown>>(path, {
    method: "GET",
    retryOnNetworkError: true,
  });

  return unwrapPayload<T>(result);
}

async function customerMutation<T>(
  path: string,
  options: {
    method?: "POST" | "PATCH" | "PUT" | "DELETE";
    formData?: FormData;
    json?: unknown;
  },
): Promise<CustomerApiResult<T>> {
  const result = await laravelRequest<Record<string, unknown>>(path, {
    method: options.method ?? "POST",
    formData: options.formData,
    json: options.json,
    retryCsrfOnce: false,
  });

  return unwrapPayload<T>(result);
}

export async function fetchDashboardOverview(): Promise<CustomerApiResult<CustomerDashboardOverview>> {
  return customerGet<CustomerDashboardOverview>("/customer?format=json");
}

export async function fetchCustomerBookings(params: {
  page?: number;
  filter?: string;
}): Promise<CustomerApiResult<{ bookings: CustomerBookingListItem[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return customerGet(`/customer/bookings?${search.toString()}`);
}

export async function fetchCustomerBookingDetail(reference: string): Promise<CustomerApiResult<BookingConfirmation>> {
  return customerGet<BookingConfirmation>(`/customer/bookings/${encodeURIComponent(reference)}?format=json`);
}

export async function requestBookingCancellation(
  bookingReference: string,
  payload: { reason?: string; cancellation_type?: string; terms_acknowledged?: boolean },
): Promise<
  CustomerApiResult<{
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
  if (payload.terms_acknowledged) formData.set("terms_acknowledged", "1");

  return customerMutation(`/customer/bookings/${encodeURIComponent(bookingReference)}/cancellations?format=json`, {
    method: "POST",
    formData,
  });
}

export async function fetchCustomerPayments(params: {
  page?: number;
  filter?: string;
}): Promise<CustomerApiResult<{ payments: CustomerPayment[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return customerGet(`/customer/payments?${search.toString()}`);
}

export async function fetchCustomerInvoices(page = 1): Promise<CustomerApiResult<{ invoices: CustomerInvoice[]; pagination: PaginatedMeta }>> {
  return customerGet(`/customer/invoices?format=json&page=${page}`);
}

export async function fetchCustomerInvoiceDetail(reference: string): Promise<CustomerApiResult<CustomerInvoice & { booking_detail_url?: string }>> {
  return customerGet(`/customer/invoices/${encodeURIComponent(reference)}?format=json`);
}

export async function fetchCustomerProfile(): Promise<CustomerApiResult<CustomerProfile>> {
  return customerGet<CustomerProfile>("/customer/profile?format=json");
}

export async function updateCustomerProfile(formData: FormData): Promise<CustomerApiResult<{ message: string }>> {
  formData.set("_method", "PATCH");
  return customerMutation("/profile", { method: "POST", formData });
}

export async function updateCustomerPassword(payload: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<CustomerApiResult<{ message: string }>> {
  const formData = new FormData();
  formData.set("current_password", payload.current_password);
  formData.set("password", payload.password);
  formData.set("password_confirmation", payload.password_confirmation);
  formData.set("_method", "PUT");
  return customerMutation("/password", { method: "POST", formData });
}

export async function fetchSupportCases(page = 1): Promise<CustomerApiResult<{ tickets: CustomerSupportCase[]; pagination: PaginatedMeta }>> {
  return customerGet(`/customer/support/tickets?format=json&page=${page}`);
}

export async function fetchSupportCreateForm(): Promise<
  CustomerApiResult<{
    categories: Array<{ value: string; label: string }>;
    bookings: Array<{ id: number; booking_reference: string; route: string; travel_date?: string | null }>;
    turnstile_required: boolean;
    submit_url: string;
  }>
> {
  return customerGet("/customer/support/tickets/create?format=json");
}

export async function fetchSupportCaseDetail(reference: string): Promise<
  CustomerApiResult<{ ticket: CustomerSupportCase; conversation: CustomerSupportReply[]; reply_url: string; close_url: string }>
> {
  return customerGet(`/customer/support/tickets/${encodeURIComponent(reference)}?format=json`);
}

export async function createSupportTicket(payload: {
  subject: string;
  category: string;
  body: string;
  booking_id?: number | null;
}): Promise<CustomerApiResult<{ redirect_url: string }>> {
  const formData = new FormData();
  formData.set("subject", payload.subject);
  formData.set("category", payload.category);
  formData.set("body", payload.body);
  if (payload.booking_id) formData.set("booking_id", String(payload.booking_id));
  return customerMutation("/customer/support/tickets?format=json", { method: "POST", formData });
}

export async function replySupportTicket(reference: string, body: string): Promise<CustomerApiResult<unknown>> {
  const formData = new FormData();
  formData.set("body", body);
  return customerMutation(`/customer/support/tickets/${encodeURIComponent(reference)}/reply?format=json`, {
    method: "POST",
    formData,
  });
}

export async function closeSupportTicket(reference: string): Promise<CustomerApiResult<unknown>> {
  const formData = new FormData();
  formData.set("_method", "PATCH");
  return customerMutation(`/customer/support/tickets/${encodeURIComponent(reference)}/close?format=json`, {
    method: "POST",
    formData,
  });
}

export async function fetchNotifications(page = 1): Promise<
  CustomerApiResult<{
    available: boolean;
    message?: string;
    unread_count: number;
    notifications: Array<Record<string, unknown>>;
    pagination: PaginatedMeta;
  }>
> {
  return customerGet(`/customer/notifications?format=json&page=${page}`);
}

export async function fetchSavedTravelers(page = 1): Promise<
  CustomerApiResult<{
    travelers: CustomerSavedTraveler[];
    default_traveler: CustomerSavedTraveler | null;
    pagination: PaginatedMeta;
    countries: Array<{ code: string; name: string }>;
    create_url: string;
  }>
> {
  return customerGet(`/customer/travelers?format=json&page=${page}`);
}

export async function fetchSavedTravelerForm(travelerId?: number): Promise<
  CustomerApiResult<{
    traveler: CustomerSavedTraveler;
    countries: Array<{ code: string; name: string }>;
    submit_url: string;
    method: "POST" | "PATCH";
  }>
> {
  const path = travelerId
    ? `/customer/travelers/${travelerId}/edit?format=json`
    : "/customer/travelers/create?format=json";
  return customerGet(path);
}

export async function saveSavedTraveler(
  payload: Record<string, string | boolean>,
  options: { travelerId?: number; method?: "POST" | "PATCH" },
): Promise<CustomerApiResult<{ redirect_url: string; traveler: CustomerSavedTraveler }>> {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    formData.set(key, typeof value === "boolean" ? (value ? "1" : "0") : value);
  });
  if (options.method === "PATCH" && options.travelerId) {
    formData.set("_method", "PATCH");
    return customerMutation(`/customer/travelers/${options.travelerId}?format=json`, { method: "POST", formData });
  }
  return customerMutation("/customer/travelers?format=json", { method: "POST", formData });
}

export async function deleteSavedTraveler(travelerId: number): Promise<CustomerApiResult<{ redirect_url: string }>> {
  const formData = new FormData();
  formData.set("_method", "DELETE");
  return customerMutation(`/customer/travelers/${travelerId}?format=json`, { method: "POST", formData });
}

export function customerApiErrorMessage(result: { ok: false; message: string; code?: string; status: number }): string {
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
  return result.message;
}
