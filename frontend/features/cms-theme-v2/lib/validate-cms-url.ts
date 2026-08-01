/** Allowed external image hosts for CMS image refs. */
export const CMS_ALLOWED_IMAGE_HOSTS = new Set([
  "jetpakistan.pk",
  "www.jetpakistan.pk",
  "cdn.jetpakistan.pk",
  "images.unsplash.com",
]);

const UNSAFE_SCHEMES = /^(javascript|data|vbscript):/i;

export type ValidatedUrl =
  | { ok: true; href: string; external: boolean }
  | { ok: false };

export function validateCmsUrl(raw: string | undefined | null): ValidatedUrl {
  if (!raw || typeof raw !== "string") {
    return { ok: false };
  }

  const trimmed = raw.trim();
  if (!trimmed || UNSAFE_SCHEMES.test(trimmed)) {
    return { ok: false };
  }

  if (trimmed.startsWith("/") && !trimmed.startsWith("//")) {
    return { ok: true, href: trimmed, external: false };
  }

  if (trimmed.startsWith("mailto:") || trimmed.startsWith("tel:")) {
    return { ok: true, href: trimmed, external: true };
  }

  try {
    const url = new URL(trimmed);
    if (url.protocol !== "http:" && url.protocol !== "https:") {
      return { ok: false };
    }
    return { ok: true, href: trimmed, external: true };
  } catch {
    return { ok: false };
  }
}

export function validateCmsImageSrc(raw: string | undefined | null): ValidatedUrl {
  if (!raw || typeof raw !== "string") {
    return { ok: false };
  }

  const trimmed = raw.trim();
  if (!trimmed || UNSAFE_SCHEMES.test(trimmed)) {
    return { ok: false };
  }

  if (trimmed.startsWith("/") && !trimmed.startsWith("//")) {
    return { ok: true, href: trimmed, external: false };
  }

  try {
    const url = new URL(trimmed);
    if (url.protocol !== "http:" && url.protocol !== "https:") {
      return { ok: false };
    }
    const host = url.hostname.toLowerCase();
    if (!CMS_ALLOWED_IMAGE_HOSTS.has(host)) {
      return { ok: false };
    }
    return { ok: true, href: trimmed, external: true };
  } catch {
    return { ok: false };
  }
}

export function externalLinkRel(external: boolean): string | undefined {
  return external ? "noopener noreferrer" : undefined;
}
