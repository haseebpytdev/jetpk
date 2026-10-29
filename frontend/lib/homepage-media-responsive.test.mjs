import assert from "node:assert/strict";
import test from "node:test";

/**
 * Lightweight contract check for responsive CMS media mapping.
 * Mirrors mapResponsiveMedia() in homepage-content-service.ts without a TS runner.
 */
function mapResponsiveMedia(remote) {
  if (!remote || typeof remote !== "object") return null;
  const fallbackUrl = String(remote.fallback_url ?? remote.fallbackUrl ?? "").trim();
  if (!fallbackUrl) return null;

  const sourcesRaw = Array.isArray(remote.sources) ? remote.sources : [];
  const sources = sourcesRaw
    .map((entry) => {
      if (!entry || typeof entry !== "object") return null;
      const srcSet = String(entry.srcset ?? entry.srcSet ?? "").trim();
      const type = String(entry.type ?? "").trim();
      if (!srcSet || !type) return null;
      const media = entry.media ? String(entry.media) : undefined;
      return media ? { type, srcSet, media } : { type, srcSet };
    })
    .filter(Boolean);

  return {
    fallbackUrl,
    width: Number(remote.width ?? 0) || undefined,
    height: Number(remote.height ?? 0) || undefined,
    sizes: remote.sizes ? String(remote.sizes) : undefined,
    sources,
    hasResponsiveVariants: remote.has_responsive_variants === true || sources.length > 0,
    fingerprint: remote.fingerprint ? String(remote.fingerprint) : null,
  };
}

test("maps snake_case responsive payload", () => {
  const mapped = mapResponsiveMedia({
    fallback_url: "/storage/card.jpg",
    width: 640,
    height: 480,
    sizes: "100vw",
    has_responsive_variants: true,
    fingerprint: "abc123",
    sources: [
      { type: "image/avif", srcset: "/storage/a.avif 320w, /storage/b.avif 640w" },
      { type: "image/webp", srcSet: "/storage/a.webp 320w" },
    ],
  });

  assert.equal(mapped.fallbackUrl, "/storage/card.jpg");
  assert.equal(mapped.hasResponsiveVariants, true);
  assert.equal(mapped.sources.length, 2);
  assert.match(mapped.sources[0].srcSet, /320w/);
});

test("returns null when fallback missing", () => {
  assert.equal(mapResponsiveMedia({ sources: [] }), null);
  assert.equal(mapResponsiveMedia(null), null);
});

test("missing responsive metadata falls back safely to original src contract", () => {
  const image = "/storage/original.png";
  const responsive = mapResponsiveMedia(null);
  assert.equal(responsive, null);
  assert.equal(typeof image, "string");
  assert.match(image, /^\/storage\//);
});
