import { test, expect } from "@playwright/test";
import {
  buildFlightSearchQueryParams,
  buildGroupSearchQueryParams,
  mapSearchModeToLaravelTripType,
} from "../features/search/utils/laravel-payload";

const passengers = { adults: 2, children: 1, infants: 1, cabin: "economy" as const };

test.describe("Laravel flight search payload contract", () => {
  test("one-way payload matches PublicFlightSearchRequest fields", () => {
    const params = buildFlightSearchQueryParams({
      mode: "one_way",
      origin: "ISB",
      destination: "DXB",
      departureDate: "2026-08-15",
      passengers,
      options: { directFlightsOnly: false, includeNearbyAirports: false, flexibleDates: false },
    });

    expect(params.get("trip_type")).toBe("one_way");
    expect(params.get("from")).toBe("ISB");
    expect(params.get("to")).toBe("DXB");
    expect(params.get("depart")).toBe("2026-08-15");
    expect(params.get("adults")).toBe("2");
    expect(params.get("children")).toBe("1");
    expect(params.get("infants")).toBe("1");
    expect(params.get("cabin")).toBe("economy");
    expect(params.get("return_date")).toBeNull();
  });

  test("return payload uses round_trip and return_date", () => {
    const params = buildFlightSearchQueryParams({
      mode: "return",
      origin: "LHE",
      destination: "JED",
      departureDate: "2026-08-15",
      returnDate: "2026-08-22",
      passengers,
      options: { directFlightsOnly: false, includeNearbyAirports: false, flexibleDates: false },
    });

    expect(mapSearchModeToLaravelTripType("return")).toBe("round_trip");
    expect(params.get("trip_type")).toBe("round_trip");
    expect(params.get("return_date")).toBe("2026-08-22");
  });

  test("direct-flight option maps to stops=direct", () => {
    const params = buildFlightSearchQueryParams({
      mode: "one_way",
      origin: "LHE",
      destination: "DXB",
      departureDate: "2026-08-15",
      passengers,
      options: { directFlightsOnly: true, includeNearbyAirports: false, flexibleDates: false },
    });

    expect(params.get("stops")).toBe("direct");
  });

  test("nearby airports maps to include_nearby=1 on origin-side search", () => {
    const params = buildFlightSearchQueryParams({
      mode: "one_way",
      origin: "LHE",
      destination: "DXB",
      departureDate: "2026-08-15",
      passengers,
      options: { directFlightsOnly: false, includeNearbyAirports: true, flexibleDates: false },
    });

    expect(params.get("include_nearby")).toBe("1");
    expect(params.get("to")).toBe("DXB");
  });

  test("flexible dates applies to outbound one-way search only", () => {
    const oneWay = buildFlightSearchQueryParams({
      mode: "one_way",
      origin: "LHE",
      destination: "DXB",
      departureDate: "2026-08-15",
      passengers,
      options: { directFlightsOnly: false, includeNearbyAirports: false, flexibleDates: true },
    });
    expect(oneWay.get("flexible_dates")).toBe("1");

    const roundTrip = buildFlightSearchQueryParams({
      mode: "return",
      origin: "LHE",
      destination: "DXB",
      departureDate: "2026-08-15",
      returnDate: "2026-08-22",
      passengers,
      options: { directFlightsOnly: false, includeNearbyAirports: false, flexibleDates: true },
    });
    expect(roundTrip.get("flexible_dates")).toBe("1");
    expect(roundTrip.get("return_date")).toBe("2026-08-22");

    const multiCity = buildFlightSearchQueryParams({
      mode: "multi_city",
      origin: "LHE",
      destination: "DXB",
      departureDate: "2026-08-15",
      segments: [
        { id: "1", from: { iata: "LHE", name: "", city: "", country: "" }, to: { iata: "DXB", name: "", city: "", country: "" }, departureDate: "2026-08-15" },
        { id: "2", from: { iata: "DXB", name: "", city: "", country: "" }, to: { iata: "LHR", name: "", city: "", country: "" }, departureDate: "2026-08-20" },
      ],
      passengers,
      options: { directFlightsOnly: false, includeNearbyAirports: false, flexibleDates: true },
    });
    expect(multiCity.get("flexible_dates")).toBeNull();
    expect(multiCity.getAll("multi_from[]")).toEqual(["LHE", "DXB"]);
  });

  test("group ticketing payload maps sector, date_from and category", () => {
    const params = buildGroupSearchQueryParams({
      sector: "UAE — Dubai",
      category: "uae",
      travelDate: "2026-09-01",
    });

    expect(params.get("sector")).toBe("UAE — Dubai");
    expect(params.get("date_from")).toBe("2026-09-01");
    expect(params.get("category")).toBe("uae");
  });

  test("group ticketing omits category when all is selected", () => {
    const params = buildGroupSearchQueryParams({
      sector: "KSA — Jeddah",
      category: "all",
      travelDate: "2026-09-01",
    });

    expect(params.get("sector")).toBe("KSA — Jeddah");
    expect(params.get("date_from")).toBe("2026-09-01");
    expect(params.get("category")).toBeNull();
  });
});
