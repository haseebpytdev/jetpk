import { laravelApiPath } from "@/services/flight-search";
import type { TurnstilePublicConfig } from "../types";

let cachedConfig: TurnstilePublicConfig | null = null;

export async function fetchTurnstileConfig(): Promise<TurnstilePublicConfig> {
  if (cachedConfig) return cachedConfig;

  try {
    const response = await fetch(laravelApiPath("/api/public/content/turnstile-config"), {
      credentials: "include",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });

    if (!response.ok) {
      return {
        enabled: false,
        site_key: null,
        response_field: "cf-turnstile-response",
      };
    }

    const body = (await response.json()) as TurnstilePublicConfig;
    cachedConfig = {
      enabled: Boolean(body.enabled),
      site_key: body.site_key ?? null,
      response_field: body.response_field || "cf-turnstile-response",
    };
    return cachedConfig;
  } catch {
    return {
      enabled: false,
      site_key: null,
      response_field: "cf-turnstile-response",
    };
  }
}

export function clearTurnstileConfigCache(): void {
  cachedConfig = null;
}
