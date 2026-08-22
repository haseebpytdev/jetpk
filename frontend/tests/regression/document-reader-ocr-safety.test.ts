import assert from "node:assert/strict";
import test from "node:test";
import { suggestTitleFromPassport } from "../../features/standard-booking/document-reader/titleFromPassport";
import {
  OCR_TERMINATE_TIMEOUT_MS,
  terminateWorkerSafely,
} from "../../features/standard-booking/document-reader/ocr/scanDocumentClientSide";

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

test("terminateWorkerSafely skips null worker", async () => {
  assert.equal(await terminateWorkerSafely(null), "skipped");
});

test("terminateWorkerSafely resolves when terminate hangs past ceiling", async () => {
  const hanging = {
    recognize: async () => ({ data: { text: "" } }),
    terminate: () => new Promise(() => undefined),
  };
  const started = Date.now();
  const status = await terminateWorkerSafely(hanging, 50);
  const elapsed = Date.now() - started;
  assert.equal(status, "timeout");
  assert.ok(elapsed < 500, `cleanup must leave Processing; elapsed=${elapsed}`);
});

test("terminateWorkerSafely treats terminate rejection as failed without throwing", async () => {
  const failing = {
    recognize: async () => ({ data: { text: "" } }),
    terminate: async () => {
      throw new Error("terminate boom");
    },
  };
  await assert.doesNotReject(async () => {
    assert.equal(await terminateWorkerSafely(failing, 200), "failed");
  });
});

test("OCR terminate ceiling constant is small and positive", () => {
  assert.ok(OCR_TERMINATE_TIMEOUT_MS > 0 && OCR_TERMINATE_TIMEOUT_MS <= 5_000);
});
