import type { FareFamilyOption } from "../types";

/**
 * Only send fare_option_key when the exact displayed option is explicitly marked
 * authoritative by the backend. Display-array membership and option_key presence
 * alone do not prove supplier selection authority.
 */
export function resolveAuthoritativeFareOptionKey(
  candidate: string | undefined,
  knownOptions: FareFamilyOption[],
): string | undefined {
  const key = candidate?.trim() ?? "";
  if (key === "" || knownOptions.length === 0) {
    return undefined;
  }

  const matchingOption = knownOptions.find((option) => option.option_key?.trim() === key);
  return matchingOption?.selection_key_authoritative === true ? key : undefined;
}
