import assert from "node:assert/strict";
import test from "node:test";
import {
  CANONICAL_JETPK_HEADER_LOGO_PATH,
  resolveHeaderLogoUrl,
} from "../../lib/branding/resolve-header-logo";

test("resolveHeaderLogoUrl falls back to canonical JetPakistan logo", () => {
  assert.equal(resolveHeaderLogoUrl(null), CANONICAL_JETPK_HEADER_LOGO_PATH);
  assert.equal(resolveHeaderLogoUrl(""), CANONICAL_JETPK_HEADER_LOGO_PATH);
});

test("resolveHeaderLogoUrl normalizes Laravel absolute client-assets URLs", () => {
  assert.equal(
    resolveHeaderLogoUrl("http://127.0.0.1:8000/client-assets/jetpk/logo/logo.png"),
    "/client-assets/jetpk/logo/logo.png",
  );
});

test("resolveHeaderLogoUrl uses organization storage uploads for the public header", () => {
  assert.equal(
    resolveHeaderLogoUrl("/storage/agencies/1/branding/logo-20260101.png"),
    "/storage/agencies/1/branding/logo-20260101.png",
  );
  assert.equal(
    resolveHeaderLogoUrl("https://jetpakistan.pk/storage/agencies/1/branding/logo.png?v=123"),
    "/storage/agencies/1/branding/logo.png?v=123",
  );
});
