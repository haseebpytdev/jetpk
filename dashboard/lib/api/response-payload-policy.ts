export function normalizeNonJsonPayload(
  contentType: string,
  bodyText: string,
  fallbackMessage: (status: number) => string,
  status: number,
): unknown | null {
  const trimmed = bodyText.trim();
  if (!trimmed) return status === 204 ? null : null;

  if (contentType.includes("application/json") || trimmed.startsWith("{") || trimmed.startsWith("[")) {
    try {
      return JSON.parse(trimmed) as unknown;
    } catch {
      return { message: fallbackMessage(status), malformed_json: true };
    }
  }

  if (trimmed.startsWith("<!DOCTYPE") || trimmed.startsWith("<html")) {
    return { message: fallbackMessage(status), html_response: true };
  }

  return { message: trimmed.slice(0, 200) };
}
