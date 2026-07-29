/**
 * Production public pages must not silently substitute fixture copy when Laravel CMS is empty.
 * Development and explicit preview builds may still use fixtures for local UI work.
 */
export function allowContentFixtures(): boolean {
  if (process.env.NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES === "true") {
    return true;
  }

  if (process.env.OTA_ALLOW_SESSION_FIXTURE === "true") {
    return true;
  }

  return process.env.NODE_ENV === "development";
}

export function resolveContentSource(
  hasCmsContent: boolean,
  fallbackSource: "fixture" | "empty" = "empty",
): "cms" | "fixture" | "empty" {
  if (hasCmsContent) {
    return "cms";
  }

  return allowContentFixtures() ? "fixture" : fallbackSource;
}
