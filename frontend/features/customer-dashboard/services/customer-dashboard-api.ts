import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import { laravelApiPath } from "@/services/flight-search";
import type {
  CustomerDashboardOverview,
  CustomerInvoice,
  CustomerPayment,
  CustomerProfile,
  CustomerSupportCase,
  CustomerSupportReply,
  PaginatedMeta,
} from "../types";
import type { BookingConfirmation } from "@/features/standard-booking/types/review-payment";
import type { CustomerBookingListItem } from "../types";

type JsonResult<T> =
  | { ok: true; data: T }
  | { ok: false; status: number; message: string; errors?: LaravelValidationErrors };

async function fetchCustomerJson<T>(path: string, init?: RequestInit): Promise<JsonResult<T>> {
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
    return { ok: false, status: 0, message: "We could not load your dashboard. Please try again." };
  }
}

export async function fetchDashboardOverview(): Promise<JsonResult<CustomerDashboardOverview>> {
  return fetchCustomerJson<CustomerDashboardOverview>("/customer?format=json");
}

export async function fetchCustomerBookings(params: {
  page?: number;
  filter?: string;
}): Promise<JsonResult<{ bookings: CustomerBookingListItem[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return fetchCustomerJson(`/customer/bookings?${search.toString()}`);
}

export async function fetchCustomerBookingDetail(reference: string): Promise<JsonResult<BookingConfirmation>> {
  return fetchCustomerJson<BookingConfirmation>(`/customer/bookings/${encodeURIComponent(reference)}?format=json`);
}

export async function fetchCustomerPayments(params: {
  page?: number;
  filter?: string;
}): Promise<JsonResult<{ payments: CustomerPayment[]; pagination: PaginatedMeta; filter: string }>> {
  const search = new URLSearchParams({ format: "json" });
  if (params.page) search.set("page", String(params.page));
  if (params.filter) search.set("filter", params.filter);
  return fetchCustomerJson(`/customer/payments?${search.toString()}`);
}

export async function fetchCustomerInvoices(page = 1): Promise<JsonResult<{ invoices: CustomerInvoice[]; pagination: PaginatedMeta }>> {
  return fetchCustomerJson(`/customer/invoices?format=json&page=${page}`);
}

export async function fetchCustomerInvoiceDetail(reference: string): Promise<JsonResult<CustomerInvoice>> {
  return fetchCustomerJson<CustomerInvoice>(`/customer/invoices/${encodeURIComponent(reference)}?format=json`);
}

export async function fetchCustomerProfile(): Promise<JsonResult<CustomerProfile>> {
  return fetchCustomerJson<CustomerProfile>("/customer/profile?format=json");
}

export async function updateCustomerProfile(formData: FormData): Promise<JsonResult<{ message: string }>> {
  const csrf = await ensureLaravelCsrfToken();
  formData.set("_method", "PATCH");
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  return fetchCustomerJson("/profile", {
    method: "POST",
    body: formData,
    headers,
  });
}

export async function updateCustomerPassword(payload: {
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

  return fetchCustomerJson("/password", {
    method: "POST",
    body: formData,
    headers,
  });
}

export async function fetchSupportCases(page = 1): Promise<JsonResult<{ tickets: CustomerSupportCase[]; pagination: PaginatedMeta }>> {
  return fetchCustomerJson(`/customer/support/tickets?format=json&page=${page}`);
}

export async function fetchSupportCreateForm(): Promise<
  JsonResult<{
    categories: Array<{ value: string; label: string }>;
    bookings: Array<{ id: number; booking_reference: string; route: string; travel_date?: string | null }>;
    turnstile_required: boolean;
    submit_url: string;
  }>
> {
  return fetchCustomerJson("/customer/support/tickets/create?format=json");
}

export async function fetchSupportCaseDetail(reference: string): Promise<
  JsonResult<{ ticket: CustomerSupportCase; conversation: CustomerSupportReply[]; reply_url: string; close_url: string }>
> {
  return fetchCustomerJson(`/customer/support/tickets/${encodeURIComponent(reference)}?format=json`);
}

export async function createSupportTicket(payload: {
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

  return fetchCustomerJson("/customer/support/tickets", {
    method: "POST",
    body: formData,
    headers,
  });
}

export async function replySupportTicket(reference: string, body: string): Promise<JsonResult<unknown>> {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  formData.set("body", body);

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  return fetchCustomerJson(`/customer/support/tickets/${encodeURIComponent(reference)}/reply`, {
    method: "POST",
    body: formData,
    headers,
  });
}

export async function fetchNotifications(page = 1): Promise<
  JsonResult<{
    available: boolean;
    message?: string;
    unread_count: number;
    notifications: Array<Record<string, unknown>>;
    pagination: PaginatedMeta;
  }>
> {
  return fetchCustomerJson(`/customer/notifications?format=json&page=${page}`);
}

export async function fetchNotificationUnreadSummary(): Promise<JsonResult<{ available: boolean; unread_count: number }>> {
  return fetchCustomerJson("/customer/notifications/unread-summary?format=json");
}
