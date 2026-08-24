import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  appendFiltersToQuery,
  CABIN_FILTER_QUERY_KEY,
  filtersToSearchParams,
  parseFiltersFromSearchParams,
} from "../features/flight-results/utils/filters";

describe("JP-BO-04G cabin filter isolation", () => {
  it("does not treat search criteria cabin as a results facet", () => {
    const params = new URLSearchParams(
      "trip_type=round_trip&cabin=economy&from=LHE&to=DXB&depart=2026-07-01&return_date=2026-07-08",
    );
    const filters = parseFiltersFromSearchParams(params);
    assert.equal(filters.cabin, undefined);
  });

  it("reads cabin_filter as the cabin facet", () => {
    const params = new URLSearchParams("cabin=economy&cabin_filter=business");
    const filters = parseFiltersFromSearchParams(params);
    assert.equal(filters.cabin, "business");
  });

  it("serializes cabin facet as cabin_filter for Laravel results/data", () => {
    const query = new URLSearchParams();
    appendFiltersToQuery(query, { cabin: "premium_economy", airline: "PK" });
    assert.equal(query.get(CABIN_FILTER_QUERY_KEY), "premium_economy");
    assert.equal(query.get("cabin"), null);
    assert.equal(query.get("airline"), "PK");

    const url = filtersToSearchParams({ cabin: "economy" });
    assert.equal(url.get("cabin_filter"), "economy");
    assert.equal(url.get("cabin"), null);
  });
});
