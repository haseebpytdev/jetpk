import { cookies } from "next/headers";

/**
 * Draft preview is gated by Laravel session + platform-admin auth.
 * When `jp_preview=1` is present, forward the browser Cookie jar to Laravel
 * so public content APIs can resolve draft without weakening authentication.
 */
export function isCmsPreviewFlag(value: string | string[] | undefined): boolean {
  const raw = Array.isArray(value) ? value[0] : value;
  return raw === "1" || raw === "true";
}

export async function cmsPreviewRequestHeaders(preview: boolean): Promise<Record<string, string>> {
  if (!preview) {
    return {};
  }

  try {
    const jar = await cookies();
    const header = jar
      .getAll()
      .map((cookie) => `${cookie.name}=${cookie.value}`)
      .join("; ");
    return header ? { Cookie: header } : {};
  } catch {
    return {};
  }
}
