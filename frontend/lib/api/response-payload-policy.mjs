/**
 * Response payload normalization helpers mirrored for regression tests.
 * Keep aligned with parseResponsePayload in laravel-action-client.ts.
 */

/**
 * @param {string} contentType
 * @param {string} bodyText
 * @param {(status: number) => string} defaultMessageForStatus
 * @param {number} status
 */
export function normalizeNonJsonPayload(contentType, bodyText, defaultMessageForStatus, status) {
  const isJson = contentType.includes("application/json");
  if (isJson) {
    try {
      // Strip UTF-8 BOM if a PHP/config file ever emits one before JSON.
      return JSON.parse(bodyText.replace(/^\uFEFF/, ""));
    } catch {
      return null;
    }
  }

  if (bodyText.trim() === "") return null;
  return { message: defaultMessageForStatus(status), _html: true };
}
