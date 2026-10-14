/**
 * Laravel portal JSON probes (same contract as frontend laravelRequest).
 */
import { BASE_URL } from "./final-11-auth-roles.mjs";

export const LARAVEL_JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
};

/**
 * @param {string} portalPath e.g. "/agent?format=json"
 */
export function laravelPortalUrl(portalPath) {
  const normalized = portalPath.startsWith("/") ? portalPath : `/${portalPath}`;
  return `${BASE_URL}/laravel${normalized}`;
}
