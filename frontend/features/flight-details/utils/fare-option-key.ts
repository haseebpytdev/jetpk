import type { FareFamilyOption } from "../types";

/**
 * Only send fare_option_key when it matches a known branded fare family on the offer.
 * Sending offer_id (card fallback) causes Laravel to return 422 invalid_fare_option.
 */
export function resolveAuthoritativeFareOptionKey(
  candidate: string | undefined,
  knownOptions: FareFamilyOption[],
): string | undefined {
  const key = candidate?.trim() ?? "";
  if (key === "" || knownOptions.length === 0) {
    return undefined;
  }

  const knownKeys = new Set(
    knownOptions
      .map((option) => option.option_key?.trim() ?? "")
      .filter((optionKey) => optionKey !== ""),
  );

  return knownKeys.has(key) ? key : undefined;
}
