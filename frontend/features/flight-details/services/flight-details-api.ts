import { laravelApiPath } from "@/services/flight-search";
import type { FlightOfferDetailsResponse } from "../types";

const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

export async function fetchOfferDetails(params: {
  searchId: string;
  offerId: string;
  fareOptionKey?: string;
  outboundKey?: string;
  comboId?: string;
  signal?: AbortSignal;
}): Promise<
  | { ok: true; data: FlightOfferDetailsResponse }
  | { ok: false; status: number; message: string; data?: Partial<FlightOfferDetailsResponse> }
> {
  const query = new URLSearchParams({
    search_id: params.searchId,
    offer_id: params.offerId,
    format: "json",
  });
  if (params.fareOptionKey) query.set("fare_option_key", params.fareOptionKey);
  if (params.outboundKey) query.set("outbound_key", params.outboundKey);
  if (params.comboId) query.set("combo_id", params.comboId);

  try {
    const response = await fetch(laravelApiPath(`/flights/results/offer?${query.toString()}`), {
      method: "GET",
      headers: JSON_HEADERS,
      credentials: "include",
      signal: params.signal,
    });

    const body = (await response.json()) as FlightOfferDetailsResponse;

    if (!response.ok || body.success === false) {
      return {
        ok: false,
        status: response.status,
        message: body.message ?? "We could not load flight details. Please try again.",
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
