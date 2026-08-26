import assert from "node:assert/strict";
import test from "node:test";
import {
  BASE_FARE_LABEL,
  BASE_FARE_OPTION_KEY,
  buildBaseOfferFareCard,
  ensureSelectableFareCatalog,
  isBaseOfferFareOption,
} from "../../features/flight-details/utils/base-offer-fare.ts";
import { resolveAuthoritativeFareOptionKey } from "../../features/flight-details/utils/fare-option-key.ts";

const offer = {
  offer_id: "offer-1",
  displayed_price: 45000,
  cabin: "Economy",
  baggage: "7kg cabin",
  refundable: false,
  can_book: true,
};

test("builds truthful Available Fare without fabricating brand family", () => {
  const card = buildBaseOfferFareCard(offer);
  assert.equal(card.brand_name, BASE_FARE_LABEL);
  assert.equal(card.option_key, BASE_FARE_OPTION_KEY);
  assert.equal(card.is_base_offer_fare, true);
  assert.equal(card.selection_key_authoritative, false);
  assert.ok(!["SMART", "BASIC", "FREEDOM"].includes(String(card.brand_name)));
});

test("ensures bookable offer always has a fare card", () => {
  const catalog = ensureSelectableFareCatalog([], offer);
  assert.equal(catalog.length, 1);
  assert.equal(isBaseOfferFareOption(catalog[0]), true);
});

test("base fare key is not an authoritative Sabre brand qualifier", () => {
  const catalog = ensureSelectableFareCatalog([], offer);
  assert.equal(resolveAuthoritativeFareOptionKey(BASE_FARE_OPTION_KEY, catalog), undefined);
});

test("keeps real branded fares when present", () => {
  const branded = [
    {
      option_key: "smart-1",
      brand_name: "SMART",
      selection_key_authoritative: true,
      can_select: true,
    },
  ];
  assert.deepEqual(ensureSelectableFareCatalog(branded, offer), branded);
});
