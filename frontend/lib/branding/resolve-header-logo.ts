/** Canonical JetPakistan header logo served from Laravel `public/client-assets`. */
export const CANONICAL_JETPK_HEADER_LOGO_PATH = "/client-assets/jetpk/logo/logo.svg";

function isClientAssetsPath(pathname: string): boolean {
  return pathname.startsWith("/client-assets/") || pathname.startsWith("client-assets/");
}

/**
 * Resolve a header logo URL for the Next public shell.
 * The JetPakistan header always uses the canonical client-assets mark unless an
 * explicit client-assets path is supplied. CMS/storage agency uploads are not
 * used here because they are not bundled with the Next production server.
 */
export function resolveHeaderLogoUrl(logoUrl?: string | null): string {
  const trimmed = logoUrl?.trim() ?? "";
  if (trimmed === "") {
    return CANONICAL_JETPK_HEADER_LOGO_PATH;
  }

  if (trimmed.startsWith("/") && isClientAssetsPath(trimmed)) {
    return trimmed;
  }

  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    try {
      const url = new URL(trimmed);
      if (isClientAssetsPath(url.pathname)) {
        return url.pathname;
      }
    } catch {
      return CANONICAL_JETPK_HEADER_LOGO_PATH;
    }
    return CANONICAL_JETPK_HEADER_LOGO_PATH;
  }

  if (trimmed.startsWith("client-assets/")) {
    return `/${trimmed.replace(/^\/+/, "")}`;
  }

  return CANONICAL_JETPK_HEADER_LOGO_PATH;
}

export function shouldUseUnoptimizedHeaderLogo(src: string): boolean {
  return (
    src.startsWith("http://") ||
    src.startsWith("https://") ||
    src.startsWith("/storage/") ||
    src.startsWith("/client-assets/")
  );
}
