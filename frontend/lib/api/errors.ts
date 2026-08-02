import type { ApiErrorCode, LaravelValidationErrors } from "./types";

export function mapStatusToErrorCode(status: number): ApiErrorCode {
  if (status === 401) return "unauthorized";
  if (status === 403) return "forbidden";
  if (status === 404) return "not_found";
  if (status === 409) return "conflict";
  if (status === 419) return "csrf_expired";
  if (status === 422) return "validation";
  if (status === 429) return "rate_limit";
  if (status >= 500) return "server";
  return "unknown";
}

export function mapFieldErrors(errors?: LaravelValidationErrors): Record<string, string> {
  if (!errors) return {};
  const mapped: Record<string, string> = {};
  Object.entries(errors).forEach(([key, messages]) => {
    mapped[key] = messages[0] ?? "Invalid value";
  });
  return mapped;
}

export function defaultErrorMessage(status: number): string {
  if (status === 401) return "Your session has expired. Please sign in again.";
  if (status === 403) return "You do not have permission to perform this action.";
  if (status === 404) return "The requested resource could not be found.";
  if (status === 409) return "This action is no longer valid. Please refresh and try again.";
  if (status === 419) return "Your session expired. Please refresh and try again.";
  if (status === 422) return "Please correct the highlighted fields and try again.";
  if (status === 429) return "Too many attempts. Please wait a moment and try again.";
  if (status >= 500) return "Something went wrong on our side. Please try again shortly.";
  return "Request failed. Please try again.";
}
