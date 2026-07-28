import type { LaravelValidationErrors } from "@/services/flight-search";

export function flattenLaravelFieldErrors(errors?: LaravelValidationErrors): string[] {
  if (!errors) return [];
  return Object.values(errors).flat();
}

export function laravelFieldError(
  errors: LaravelValidationErrors | undefined,
  keys: string[],
): string | undefined {
  if (!errors) return undefined;
  for (const key of keys) {
    const messages = errors[key];
    if (messages?.[0]) return messages[0];
  }
  return undefined;
}
