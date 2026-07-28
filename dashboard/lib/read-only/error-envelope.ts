import type { ReadOnlyErrorCode, ReadOnlyErrorEnvelope } from "@/types/read-only-integration";
import { READ_ONLY_SCHEMA_VERSION } from "@/types/read-only-integration";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";

const SAFE_MESSAGES: Record<ReadOnlyErrorCode, string> = {
  unauthenticated: "Sign in is required to view this data.",
  forbidden: "You do not have permission to view this data.",
  validation_error: "The request could not be processed. Check your filters and try again.",
  not_found: "The requested record was not found.",
  unavailable: "The data service is temporarily unavailable.",
  timeout: "The request timed out. Try again shortly.",
  rate_limited: "Too many requests. Wait a moment and try again.",
  internal_error: "Something went wrong while loading data.",
};

export function createReadOnlyErrorEnvelope(params: {
  code: ReadOnlyErrorCode;
  referenceIdSafe: string;
  message?: string;
  details?: Record<string, string[]>;
}): ReadOnlyErrorEnvelope {
  return {
    error: {
      code: params.code,
      message: params.message ?? SAFE_MESSAGES[params.code],
      referenceIdSafe: params.referenceIdSafe,
      details: params.details,
    },
    meta: {
      source: resolveDataSourceMode(),
      schemaVersion: READ_ONLY_SCHEMA_VERSION,
    },
  };
}

export function sanitizeErrorMessage(message: string): string {
  const blockedPatterns = [
    /sql/i,
    /stack trace/i,
    /\.php\b/i,
    /vendor\//i,
    /password/i,
    /token/i,
    /cookie/i,
    /session[_\s]?id/i,
    /pcc/i,
    /lniata/i,
    /exception class/i,
  ];
  for (const pattern of blockedPatterns) {
    if (pattern.test(message)) {
      return SAFE_MESSAGES.internal_error;
    }
  }
  return message.slice(0, 280);
}

export function mapHttpStatusToErrorCode(status: number): ReadOnlyErrorCode {
  if (status === 401) return "unauthenticated";
  if (status === 403) return "forbidden";
  if (status === 404) return "not_found";
  if (status === 408) return "timeout";
  if (status === 422) return "validation_error";
  if (status === 429) return "rate_limited";
  if (status === 503) return "unavailable";
  return "internal_error";
}
