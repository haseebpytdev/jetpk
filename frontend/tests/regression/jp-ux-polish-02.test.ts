import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";

const root = join(__dirname, "../..");

function readFrontend(rel: string): string {
  return readFileSync(join(root, rel), "utf8");
}

describe("JP-UX-POLISH-02 contracts", () => {
  it("paired return strip uses Departure/Arrival badges without visible Outbound/Return labels", () => {
    const source = readFrontend("features/flight-results/components/PairReturnCard.tsx");
    expect(source).toContain('data-testid="paired-strip-departure-badge"');
    expect(source).toContain('data-testid="paired-strip-arrival-badge"');
    expect(source).toContain("Departure");
    expect(source).toContain("Arrival");
    expect(source).not.toMatch(/>\s*Outbound\s*</);
    expect(source).not.toMatch(/>\s*Return\s*</);
    expect(source).toContain("sr-only");
  });

  it("traveler page does not regress READY to full skeleton on soft reload", () => {
    const source = readFrontend("features/standard-booking/components/PassengerDetailsPage.tsx");
    expect(source).toContain("showInitialSkeleton");
    expect(source).toContain("loading && !context");
    expect(source).toContain("loadContext({ soft: true })");
    expect(source).toContain('data-testid="passenger-skeleton"');
  });

  it("checkout layout keeps shared public footer", () => {
    const source = readFrontend("app/(checkout)/layout.tsx");
    expect(source).not.toContain("hideFooter");
    expect(source).toContain("PublicShell");
  });

  it("airline logo mark is square transparent radius 0", () => {
    const source = readFrontend("components/ui/AirlineLogoMark.tsx");
    expect(source).toContain('data-logo-frame="square-none"');
    expect(source).toContain('data-logo-radius="0"');
    expect(source).toContain("borderRadius: 0");
    expect(source).toContain('background: "transparent"');
  });

  it("homepage hero removes orphan flight-path decoration and uses compact spacer", () => {
    const source = readFrontend("features/public-visual/hero/PublicHero.tsx");
    expect(source).not.toContain("AnimatedFlightPath");
    expect(source).toContain('data-testid="homepage-hero-overlap-spacer"');
    expect(source).toContain("h-6 sm:h-8");
  });

  it("group result cards use AirlineLogoMark square stage", () => {
    const source = readFrontend("features/group-ticketing/components/GroupResultCard.tsx");
    expect(source).toContain("AirlineLogoMark");
    expect(source).not.toContain("rounded-jp-md border border-jp-border bg-white");
  });
});
