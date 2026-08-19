import assert from "node:assert/strict";
import test from "node:test";

const CANONICAL_JETPK_HEADER_LOGO_PATH = "/client-assets/jetpk/logo/logo.svg";

function isClientAssetsPath(pathname) {
  return pathname.startsWith("/client-assets/") || pathname.startsWith("client-assets/");
}

function resolveHeaderLogoUrl(logoUrl) {
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

test("resolveHeaderLogoUrl falls back to canonical JetPakistan logo", () => {
  assert.equal(resolveHeaderLogoUrl(null), CANONICAL_JETPK_HEADER_LOGO_PATH);
  assert.equal(resolveHeaderLogoUrl(""), CANONICAL_JETPK_HEADER_LOGO_PATH);
});

test("resolveHeaderLogoUrl normalizes Laravel absolute client-assets URLs", () => {
  assert.equal(
    resolveHeaderLogoUrl("http://127.0.0.1:8000/client-assets/jetpk/logo/logo.svg"),
    "/client-assets/jetpk/logo/logo.svg",
  );
});

test("resolveHeaderLogoUrl ignores storage uploads for Next header shell", () => {
  assert.equal(
    resolveHeaderLogoUrl("/storage/branding/jp-dash-03-logo.png"),
    CANONICAL_JETPK_HEADER_LOGO_PATH,
  );
});
