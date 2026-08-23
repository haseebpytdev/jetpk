import { cookies } from "next/headers";

/**
 * Draft preview is gated by Laravel (admin session OR short-lived signed
 * page-scoped `jp_preview_token`). When preview mode is active, forward the
 * browser Cookie jar and/or preview token so public content APIs can resolve
 * draft without weakening authentication or publishing.
 */
export function isCmsPreviewFlag(value: string | string[] | undefined): boolean {
  const raw = Array.isArray(value) ? value[0] : value;
  return raw === "1" || raw === "true";
}

export function readCmsPreviewToken(value: string | string[] | undefined): string | null {
  const raw = Array.isArray(value) ? value[0] : value;
  if (typeof raw !== "string") {
    return null;
  }
  const token = raw.trim();
  return token !== "" ? token : null;
}

export async function cmsPreviewRequestHeaders(
  preview: boolean,
  previewToken?: string | null,
): Promise<Record<string, string>> {
  if (!preview) {
    return {};
  }

  const headers: Record<string, string> = {};

  if (previewToken && previewToken.trim() !== "") {
    headers["X-JP-Preview-Token"] = previewToken.trim();
  }

  try {
    const jar = await cookies();
    const header = jar
      .getAll()
      .map((cookie) => `${cookie.name}=${cookie.value}`)
      .join("; ");
    if (header) {
      headers.Cookie = header;
    }
  } catch {
    // cookies() is unavailable outside a request context — token header alone may still work.
  }

  return headers;
}
