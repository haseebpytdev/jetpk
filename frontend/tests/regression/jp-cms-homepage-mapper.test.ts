import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  mapDestinations,
  mapFeaturedDeals,
  mapRoutes,
  mapSupportCta,
} from "../../features/public-visual/services/homepage-content-service";

describe("homepage CMS mapper", () => {
  it("maps trending routes, destinations, featured deals, and support media", () => {
    const routes = mapRoutes([
      { id: "r1", from: "LHE", to: "DXB", price_label: "PKR 45,000", search_url: "/flights/results?from=LHE&to=DXB", image: "https://cdn.example/route.jpg" },
    ]);
    const destinations = mapDestinations([
      { id: "d1", code: "IST", title: "Istanbul", image: "https://cdn.example/ist.jpg", price_label: "From PKR 90,000", href: "/flights/results?to=IST" },
    ]);
    const deals = mapFeaturedDeals([
      { id: "f1", airline: "PK", from: "ISB", to: "JED", depart: "08:00", arrive: "12:00", duration: "4h", stops: 0, price_label: "PKR 120,000", image: "https://cdn.example/deal.jpg", href: "/flights/results" },
    ]);
    const support = mapSupportCta({
      enabled: true,
      title: "Human Support",
      subtitle: "Talk to JetPakistan",
      call_enabled: true,
      call_label: "Call",
      call_href: "tel:+92000000000",
    });

    assert.equal(routes[0]?.image, "https://cdn.example/route.jpg");
    assert.equal(destinations[0]?.image, "https://cdn.example/ist.jpg");
    assert.equal(deals[0]?.image, "https://cdn.example/deal.jpg");
    assert.equal(support.enabled, true);
    assert.equal(support.title, "Human Support");
  });

  it("does not invent commercial hrefs when CMS omits them", () => {
    const destinations = mapDestinations([{ code: "DXB", title: "Dubai" }]);
    assert.equal(destinations[0]?.href, null);
    assert.equal(destinations[0]?.image, null);
  });
});
