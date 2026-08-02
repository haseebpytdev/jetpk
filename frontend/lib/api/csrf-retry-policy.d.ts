export type CsrfRetryResult = {
  ok: boolean;
  code?: string;
};

export function shouldRetryAfterCsrfExpired(
  result: CsrfRetryResult,
  method: string,
  retryCsrfOnce: boolean,
): boolean;

export function pathAllowsCsrfAutoRetry(path: string): boolean;

export const CSRF_NO_AUTO_RETRY_PATH_PREFIXES: readonly string[];

export function simulateCsrfRetryPolicy(options: {
  method?: string;
  retryCsrfOnce?: boolean;
  fetchImpl: (ctx: {
    attempt: number;
    forceRefresh: boolean;
  }) => Promise<{
    ok: boolean;
    code?: string;
    status?: number;
    path?: string;
  }>;
}): Promise<{
  result: {
    ok: boolean;
    code?: string;
    status?: number;
    path?: string;
  };
  attempts: number;
  csrfRefreshed: boolean;
}>;
