import { appConfig } from "@/lib/config";
import { laravelApiPath } from "@/services/flight-search";
import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type {
  ActiveResultsFilters,
  FlightResultsDataResponse,
  FlightSearchInitFullResponse,
  RevalidateOfferResponse,
  ReturnOptionsDataResponse,
} from "../types";

const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

export type FetchResultsParams = {
  searchId: string;
  page?: number;
  perPage?: number;
  sort?: string;
  filters?: ActiveResultsFilters;
  signal?: AbortSignal;
};

export async function fetchFlightResultsData(
  params: FetchResultsParams,
): Promise<
  | { ok: true; data: FlightResultsDataResponse }
  | { ok: false; status: number; message: string; data?: Partial<FlightResultsDataResponse> }
> {
  const query = new URLSearchParams();
  query.set("search_id", params.searchId);
  query.set("page", String(params.page ?? 1));
  query.set("per_page", String(params.perPage ?? 12));
  if (params.sort) {
    query.set("sort", params.sort);
  }
  if (params.filters) {
    Object.entries(params.filters).forEach(([key, value]) => {
      if (value) query.set(key, value);
    });
  }

  try {
    const response = await fetch(laravelApiPath(`/flights/results/data?${query.toString()}`), {
      method: "GET",
      headers: JSON_HEADERS,
      credentials: "include",
      signal: params.signal,
    });

    const body = (await response.json()) as FlightResultsDataResponse & { message?: string };

    if (response.status === 410) {
      return {
        ok: false,
        status: 410,
        message: body.message ?? "This fare search has expired. Please search again.",
        data: body,
      };
    }

    if (!response.ok) {
      return {
        ok: false,
        status: response.status,
        message: body.message ?? "We could not load flight results. Please try again.",
        data: body,
      };
    }

    return { ok: true, data: body };
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      return { ok: false, status: 0, message: "Request cancelled." };
    }
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export async function fetchReturnOptionsData(params: {
  searchId: string;
  outboundKey: string;
  page?: number;
  perPage?: number;
  signal?: AbortSignal;
}): Promise<
  | { ok: true; data: ReturnOptionsDataResponse }
  | { ok: false; status: number; message: string }
> {
  const query = new URLSearchParams({
    search_id: params.searchId,
    outbound_key: params.outboundKey,
    page: String(params.page ?? 1),
    per_page: String(params.perPage ?? 12),
  });

  try {
    const response = await fetch(laravelApiPath(`/flights/return-options/data?${query.toString()}`), {
      method: "GET",
      headers: JSON_HEADERS,
      credentials: "include",
      signal: params.signal,
    });

    const body = (await response.json()) as ReturnOptionsDataResponse & { message?: string };

    if (!response.ok) {
      return {
        ok: false,
        status: response.status,
        message: body.message ?? "Unable to load return flight options.",
      };
    }

    return { ok: true, data: body };
  } catch {
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export async function revalidateOffer(params: {
  searchId: string;
  offerId: string;
  selectedFareOptionId?: string;
  acceptFareChange?: boolean;
}): Promise<
  | { ok: true; data: RevalidateOfferResponse }
  | { ok: false; status: number; message: string; data?: RevalidateOfferResponse }
> {
  const csrf = await ensureLaravelCsrfToken();
  const form = new FormData();
  form.append("search_id", params.searchId);
  form.append("offer_id", params.offerId);
  form.append("flight_id", params.offerId);
  if (params.selectedFareOptionId) {
    form.append("selected_fare_option_id", params.selectedFareOptionId);
  }
  if (params.acceptFareChange) {
    form.append("accept_fare_change", "1");
  }

  try {
    const response = await fetch(laravelApiPath("/flights/results/revalidate-offer"), {
      method: "POST",
      headers: {
        ...JSON_HEADERS,
        ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
      },
      credentials: "include",
      body: form,
    });

    const body = (await response.json()) as RevalidateOfferResponse;

    if (!response.ok || !body.success) {
      return {
        ok: false,
        status: response.status,
        message: body.message ?? "We could not confirm this fare with the airline.",
        data: body,
      };
    }

    return { ok: true, data: body };
  } catch {
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export function absoluteLaravelHandoffUrl(pathOrUrl: string): string {
  if (pathOrUrl.startsWith("http://") || pathOrUrl.startsWith("https://")) {
    return pathOrUrl;
  }
  const base = appConfig.laravelUrl.replace(/\/$/, "");
  const normalized = pathOrUrl.startsWith("/") ? pathOrUrl : `/${pathOrUrl}`;
  return `${base}${normalized}`;
}

export function buildCheckoutHandoffUrl(
  selectUrl: string,
  offerId: string,
  fareOptionKey: string,
  searchId: string,
): string {
  try {
    const url = new URL(selectUrl, absoluteLaravelHandoffUrl("/"));
    url.searchParams.set("offer_id", offerId);
    url.searchParams.set("flight_id", offerId);
    url.searchParams.set("fare_option_key", fareOptionKey);
    url.searchParams.set("search_id", searchId);
    return url.toString();
  } catch {
    const sep = selectUrl.includes("?") ? "&" : "?";
    return `${absoluteLaravelHandoffUrl(selectUrl)}${sep}offer_id=${encodeURIComponent(offerId)}&flight_id=${encodeURIComponent(offerId)}&fare_option_key=${encodeURIComponent(fareOptionKey)}&search_id=${encodeURIComponent(searchId)}`;
  }
}

export async function submitReturnComboSelection(params: {
  searchId: string;
  comboId: string;
  outboundKey: string;
  fareOptionKey?: string;
  outboundFareOptionKey?: string;
  returnFareOptionKey?: string;
}): Promise<void> {
  const csrf = await ensureLaravelCsrfToken();
  const form = document.createElement("form");
  form.method = "POST";
  form.action = absoluteLaravelHandoffUrl("/flights/select-return-combo");
  form.style.display = "none";

  const fields: Record<string, string> = {
    search_id: params.searchId,
    combo_id: params.comboId,
    outbound_key: params.outboundKey,
  };
  if (params.fareOptionKey) fields.fare_option_key = params.fareOptionKey;
  if (params.outboundFareOptionKey) fields.outbound_fare_option_key = params.outboundFareOptionKey;
  if (params.returnFareOptionKey) fields.return_fare_option_key = params.returnFareOptionKey;
  if (csrf) {
    const tokenInput = document.createElement("input");
    tokenInput.type = "hidden";
    tokenInput.name = "_token";
    tokenInput.value = csrf;
    form.appendChild(tokenInput);
  }

  Object.entries(fields).forEach(([name, value]) => {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });

  document.body.appendChild(form);
  form.submit();
}

export type { FlightSearchInitFullResponse };
