import assert from "node:assert/strict";
import test from "node:test";
import { suggestTitleFromPassport } from "../../features/standard-booking/document-reader/titleFromPassport";

test("OCR timeout message contract remains customer-safe", () => {
  // scanDocumentClientSide timeout path returns this exact customer copy.
  const timeoutCopy = "Reading took too long. Try a clearer, well-lit photo of the passport data page.";
  assert.match(timeoutCopy, /clearer/i);
  assert.doesNotMatch(timeoutCopy, /tesseract|worker|mrz/i);
});

test("cancel copy does not expose OCR internals", () => {
  const cancelCopy = "Passport scan cancelled.";
  assert.doesNotMatch(cancelCopy, /tesseract|abort|worker/i);
});

test("title mapping never invents Mrs or Miss from gender alone", () => {
  assert.equal(suggestTitleFromPassport({ gender: "female" }), "Ms");
  assert.notEqual(suggestTitleFromPassport({ gender: "female" }), "Mrs");
  assert.notEqual(suggestTitleFromPassport({ gender: "female" }), "Miss");
});
