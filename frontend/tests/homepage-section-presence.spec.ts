/**
 * Homepage approved-section presence contract for FINAL reconciliation.
 * Asserts Destinations on the Rise is composed beside Trending Routes.
 */
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

const homepageContentPath = join(
  process.cwd(),
  "features/home/components/HomepageContent.tsx",
);

describe("homepage approved sections", () => {
  it("renders DestinationsSection between Routes and Featured deals", () => {
    const source = readFileSync(homepageContentPath, "utf8");
    expect(source).toContain("DestinationsSection");
    expect(source).toContain("<RoutesSection");
    expect(source).toContain("<DestinationsSection");
    expect(source).toContain("<FeaturedOffersSection");

    const routesIdx = source.indexOf("<RoutesSection");
    const destinationsIdx = source.indexOf("<DestinationsSection");
    const featuredIdx = source.indexOf("<FeaturedOffersSection");

    expect(routesIdx).toBeGreaterThan(-1);
    expect(destinationsIdx).toBeGreaterThan(routesIdx);
    expect(featuredIdx).toBeGreaterThan(destinationsIdx);
  });
});
