import { parseTd3Mrz, type MrzParseResult } from "../mrz/parseMrz";

export type DocumentScanSource = {
  kind: "text" | "image";
  text?: string;
  file?: File;
};

export type DocumentScanOptions = {
  signal?: AbortSignal;
  /** Hard ceiling for OCR; defaults to 45s. */
  timeoutMs?: number;
  onProgress?: (progress: { status: string; progress: number }) => void;
};

const DEFAULT_TIMEOUT_MS = 45_000;
const MAX_IMAGE_BYTES = 12 * 1024 * 1024;
const MAX_DIMENSION = 1800;
const MIN_DIMENSION = 200;

/** Self-hosted OCR assets under /tesseract (never third-party CDN at runtime). */
const TESSERACT_ASSET_PATH = "/tesseract";

function fail(message: string): MrzParseResult {
  return {
    ok: false,
    fields: {},
    confidence: [],
    warnings: [message],
    checkDigitsValid: false,
    rawLines: [],
  };
}

function assertNotAborted(signal?: AbortSignal): void {
  if (signal?.aborted) {
    throw new DOMException("Passport scan cancelled.", "AbortError");
  }
}

async function withTimeout<T>(promise: Promise<T>, timeoutMs: number, signal?: AbortSignal): Promise<T> {
  assertNotAborted(signal);
  let timer: ReturnType<typeof setTimeout> | undefined;
  let onAbort: (() => void) | undefined;

  try {
    return await Promise.race([
      promise,
      new Promise<T>((_, reject) => {
        timer = setTimeout(() => {
          reject(new Error("OCR_TIMEOUT"));
        }, timeoutMs);
        if (signal) {
          onAbort = () => reject(new DOMException("Passport scan cancelled.", "AbortError"));
          signal.addEventListener("abort", onAbort, { once: true });
        }
      }),
    ]);
  } finally {
    if (timer) clearTimeout(timer);
    if (signal && onAbort) signal.removeEventListener("abort", onAbort);
  }
}

async function loadImageElement(file: File): Promise<HTMLImageElement> {
  const objectUrl = URL.createObjectURL(file);
  try {
    const image = await new Promise<HTMLImageElement>((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error("Could not read that image. Try a clearer passport photo."));
      img.src = objectUrl;
    });
    return image;
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}

/**
 * Downscale large passport photos for OCR reliability and bounded runtime.
 * Returns a canvas data URL suitable for tesseract.recognize.
 */
export async function preprocessPassportImage(file: File): Promise<{ dataUrl: string; width: number; height: number }> {
  if (!file.type.startsWith("image/")) {
    throw new Error("Please choose a passport photo (JPG or PNG).");
  }
  if (file.size <= 0) {
    throw new Error("That image file is empty. Try another photo.");
  }
  if (file.size > MAX_IMAGE_BYTES) {
    throw new Error("Image is too large. Use a photo under 12 MB.");
  }

  const image = await loadImageElement(file);
  const width = image.naturalWidth || image.width;
  const height = image.naturalHeight || image.height;
  if (width < MIN_DIMENSION || height < MIN_DIMENSION) {
    throw new Error("Image is too small. Capture the full passport data page more clearly.");
  }

  const scale = Math.min(1, MAX_DIMENSION / Math.max(width, height));
  const targetW = Math.max(1, Math.round(width * scale));
  const targetH = Math.max(1, Math.round(height * scale));

  const canvas = document.createElement("canvas");
  canvas.width = targetW;
  canvas.height = targetH;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  if (!ctx) {
    throw new Error("This device cannot process passport images.");
  }
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, targetW, targetH);
  ctx.drawImage(image, 0, 0, targetW, targetH);

  // Mild contrast boost for MRZ OCR without shipping the image anywhere.
  try {
    const imageData = ctx.getImageData(0, 0, targetW, targetH);
    const data = imageData.data;
    for (let i = 0; i < data.length; i += 4) {
      const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
      const boosted = gray < 140 ? Math.max(0, gray * 0.85) : Math.min(255, gray * 1.08);
      data[i] = boosted;
      data[i + 1] = boosted;
      data[i + 2] = boosted;
    }
    ctx.putImageData(imageData, 0, 0);
  } catch {
    // Some browsers block getImageData on tainted canvases; keep the drawn image.
  }

  return {
    dataUrl: canvas.toDataURL("image/jpeg", 0.92),
    width: targetW,
    height: targetH,
  };
}

/**
 * CLIENT_SIDE document scan. Images never leave the browser and are never persisted.
 * tesseract.js is loaded only when an image is provided (dynamic import).
 * Worker/core/lang assets are served from self-hosted /tesseract paths.
 */
export async function scanDocumentClientSide(
  source: DocumentScanSource,
  options: DocumentScanOptions = {},
): Promise<MrzParseResult> {
  if (source.kind === "text") {
    return parseTd3Mrz(source.text ?? "");
  }

  if (!source.file) {
    return fail("No document image selected.");
  }

  if (source.file.type.startsWith("text/") || /\.txt$/i.test(source.file.name)) {
    const text = await source.file.text();
    return parseTd3Mrz(text);
  }

  const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  let worker: { recognize: (image: string) => Promise<{ data: { text: string } }>; terminate: () => Promise<void>; setParameters?: (p: Record<string, unknown>) => Promise<void> } | null = null;

  try {
    assertNotAborted(options.signal);
    options.onProgress?.({ status: "preparing", progress: 0.05 });

    const prepared = await withTimeout(preprocessPassportImage(source.file), Math.min(10_000, timeoutMs), options.signal);
    options.onProgress?.({ status: "loading_ocr", progress: 0.15 });

    const { createWorker } = await import("tesseract.js");
    assertNotAborted(options.signal);

    worker = await withTimeout(
      createWorker("eng", 1, {
        workerPath: `${TESSERACT_ASSET_PATH}/worker.min.js`,
        corePath: `${TESSERACT_ASSET_PATH}/tesseract-core-simd-lstm.wasm.js`,
        langPath: TESSERACT_ASSET_PATH,
        gzip: true,
        logger: (message: { status?: string; progress?: number }) => {
          if (typeof message.progress === "number") {
            options.onProgress?.({
              status: message.status ?? "recognizing",
              progress: Math.min(0.95, 0.2 + message.progress * 0.75),
            });
          }
        },
      }) as Promise<NonNullable<typeof worker>>,
      timeoutMs,
      options.signal,
    );

    await worker.setParameters?.({
      tessedit_pageseg_mode: "6",
      preserve_interword_spaces: "1",
    });

    options.onProgress?.({ status: "recognizing", progress: 0.35 });
    const {
      data: { text },
    } = await withTimeout(worker.recognize(prepared.dataUrl), timeoutMs, options.signal);

    options.onProgress?.({ status: "parsing", progress: 0.95 });
    const result = parseTd3Mrz(text);
    if (!result.ok && result.warnings.length === 0) {
      result.warnings.push("Could not read passport details. Try a clearer photo of the data page.");
    } else if (!result.ok) {
      result.warnings.push("Could not read a complete passport MRZ. Try a clearer photo and retry.");
    }
    options.onProgress?.({ status: "done", progress: 1 });
    return result;
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      return fail("Passport scan cancelled.");
    }
    if (error instanceof Error && error.message === "OCR_TIMEOUT") {
      return fail("Reading took too long. Try a clearer, well-lit photo of the passport data page.");
    }
    if (error instanceof Error && error.message && !error.message.includes("createWorker")) {
      return fail(error.message);
    }
    return fail(
      "Could not read the passport on this device. Try a clearer photo — images are never uploaded.",
    );
  } finally {
    if (worker) {
      try {
        await worker.terminate();
      } catch {
        // Deterministic cleanup: ignore terminate failures after timeout/abort.
      }
    }
  }
}
