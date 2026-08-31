import { appConfig } from "@/lib/config";
import type { GroupSearchDraft } from "@/features/search/types";
import {
  buildFlightResultsPagePath,
  buildFlightSearchInitPath,
  buildGroupSearchQueryParams,
  buildGroupSearchPagePath,
  buildFlightSearchQueryParams,
  type FlightSearchPayloadInput,
} from "@/features/search/utils/laravel-payload";

export type LaravelValidationErrors = Record<string, string[]>;

export type FlightSearchInitResponse = {
  search_id: string;
  status?: string;
  results_page_url: string;
  initial_results_url: string;
  summary?: { text?: string };
  warnings?: string[];
};

export type SearchSubmitState =
  | { status: "idle" }
  | { status: "submitting" }
  | { status: "error"; message: string; fieldErrors?: LaravelValidationErrors }
  | { status: "redirecting"; targetUrl: string };

/**
 * Same-origin relative path in production when Nginx proxies `/laravel/*` to Laravel.
 * Local dev uses Next.js rewrite in `next.config.ts`.
 */
export function laravelApiPath(path: string): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  // Presenters may already emit /laravel/... mutation URLs; never double-prefix.
  if (normalized === "/laravel" || normalized.startsWith("/laravel/")) {
    return normalized;
  }

  return `/laravel${normalized}`;
}

export function absoluteLaravelUrl(path: string): string {
  const base = appConfig.laravelUrl.replace(/\/$/, "");
  const normalized = path.startsWith("/") ? path : `/${path}`;
  return `${base}${normalized}`;
}

function resolveResultsPath(data: FlightSearchInitResponse, fallbackQuery: URLSearchParams): string {
  const params = new URLSearchParams(fallbackQuery);
  if (data.search_id) {
    params.set("search_id", data.search_id);
  }
  return buildFlightResultsPagePath(params);
}

export async function initFlightSearch(
  input: FlightSearchPayloadInput,
  signal?: AbortSignal,
): Promise<
  | { ok: true; data: FlightSearchInitResponse; resultsPath: string }
  | { ok: false; message: string; fieldErrors?: LaravelValidationErrors; status?: number }
> {
  const query = buildFlightSearchQueryParams(input);
  const initPath = buildFlightSearchInitPath(query);
  const apiUrl = laravelApiPath(initPath);

  try {
    const response = await fetch(apiUrl, {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "include",
      signal,
    });

    if (response.status === 422) {
      const body = (await response.json()) as { message?: string; errors?: LaravelValidationErrors };
      return {
        ok: false,
        message: body.message ?? "Please fix the highlighted search fields.",
        fieldErrors: body.errors,
        status: 422,
      };
    }

    if (response.status === 401) {
      return { ok: false, message: "Your session expired. Please sign in and try again.", status: 401 };
    }

    if (!response.ok) {
      return {
        ok: false,
        message: "We could not start your flight search. Please try again.",
        status: response.status,
      };
    }

    const data = (await response.json()) as FlightSearchInitResponse;

    return { ok: true, data, resultsPath: resolveResultsPath(data, query) };
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      return { ok: false, message: "Search cancelled." };
    }
    return { ok: false, message: "Network error. Check your connection and try again." };
  }
}

/** Navigate to the Next.js operational results route (JP-FE-05). */
export function handoffToFlightResults(resultsPath: string): void {
  const target = resultsPath.startsWith("http")
    ? (() => {
        try {
          const url = new URL(resultsPath);
          return `${url.pathname}${url.search}`;
        } catch {
          return resultsPath;
        }
      })()
    : resultsPath;

  if (typeof document !== "undefined") {
    document.body.setAttribute("data-handoff-url", target);
  }
  window.location.assign(target.startsWith("/") ? target : `/${target}`);
}

/** @deprecated Use handoffToFlightResults — retained for group and legacy callers. */
export function handoffToLaravelResults(resultsPath: string): void {
  handoffToFlightResults(resultsPath);
}

/** Navigate to the Next.js operational group search route (JP-FE-07). */
export function handoffToGroupSearch(query: URLSearchParams): void {
  const target = buildGroupSearchPagePath(query);
  if (typeof document !== "undefined") {
    document.body.setAttribute("data-handoff-url", target);
  }
  window.location.assign(target);
}

export function buildGroupHandoffQuery(draft: Omit<GroupSearchDraft, "submittedAt">): URLSearchParams {
  return buildGroupSearchQueryParams({
    airline: draft.airline,
    sector: draft.sector,
    category: draft.category,
    travelDate: draft.travelDate,
  });
}
