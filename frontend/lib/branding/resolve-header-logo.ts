/** Canonical JetPakistan header logo fallback when no organization logo is configured. */
export const CANONICAL_JETPK_HEADER_LOGO_PATH = "/client-assets/jetpk/logo/logo.png";

function isClientAssetsPath(pathname: string): boolean {
  return pathname.startsWith("/client-assets/") || pathname.startsWith("client-assets/");
}

function isOrganizationStoragePath(pathname: string): boolean {
  return (
    pathname.startsWith("/storage/") ||
    pathname.startsWith("storage/") ||
    pathname.includes("/agencies/") ||
    pathname.includes("/branding/")
  );
}

function normalizeRelativePath(path: string): string {
  return path.startsWith("/") ? path : `/${path.replace(/^\/+/, "")}`;
}

/**
 * Resolve a header logo URL for the Next public shell.
 * Organization logos from Laravel public storage are authoritative; static
 * client-assets remain the fallback when no valid organization logo exists.
 */
export function resolveHeaderLogoUrl(logoUrl?: string | null): string {
  const trimmed = logoUrl?.trim() ?? "";
  if (trimmed === "") {
    return CANONICAL_JETPK_HEADER_LOGO_PATH;
  }

  if (trimmed.startsWith("/") && isClientAssetsPath(trimmed)) {
    return trimmed;
  }

  if (trimmed.startsWith("/") && isOrganizationStoragePath(trimmed)) {
    return trimmed;
  }

  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    try {
      const url = new URL(trimmed);
      if (isClientAssetsPath(url.pathname) || isOrganizationStoragePath(url.pathname)) {
        return normalizeRelativePath(`${url.pathname}${url.search}`);
      }
    } catch {
      return CANONICAL_JETPK_HEADER_LOGO_PATH;
    }

    return CANONICAL_JETPK_HEADER_LOGO_PATH;
  }

  if (trimmed.startsWith("client-assets/")) {
    return normalizeRelativePath(trimmed);
  }

  if (trimmed.startsWith("storage/")) {
    return normalizeRelativePath(trimmed);
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
