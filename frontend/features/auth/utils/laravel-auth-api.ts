/**
 * Auth feature re-exports of the shared Laravel action client.
 * New code should import from @/lib/api directly.
 */
export type { LaravelValidationErrors } from "@/lib/api";
export {
  buildCookieHeader,
  ensureLaravelCsrfToken,
  laravelJsonFetch,
  mapFieldErrors,
} from "@/lib/api";
