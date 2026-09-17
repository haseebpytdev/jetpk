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
/** Bound worker.terminate so a hung worker cannot keep the UI in Processing. */
export const OCR_TERMINATE_TIMEOUT_MS = 2_000;
const MAX_IMAGE_BYTES = 12 * 1024 * 1024;
const MAX_DIMENSION = 1800;
const MIN_DIMENSION = 200;

/** Self-hosted OCR assets under /tesseract (never third-party CDN at runtime). */
const TESSERACT_ASSET_PATH = "/tesseract";

const CUSTOMER_FAILURE =
  "We couldn't clearly read the passport. Try a sharper photo with the full data page visible.";

type LocalOcrWorker = {
  recognize: (image: string) => Promise<{ data: { text: string } }>;
  terminate: () => Promise<unknown>;
  setParameters?: (params: Record<string, unknown>) => Promise<unknown>;
};

export type PassportImageVariants = {
  fullPage: string;
  mrzCrop: string;
  mrzHighContrast: string;
  width: number;
  height: number;
};

export async function terminateWorkerSafely(
  worker: LocalOcrWorker | null | undefined,
  timeoutMs: number = OCR_TERMINATE_TIMEOUT_MS,
): Promise<"terminated" | "timeout" | "failed" | "skipped"> {
  if (!worker) return "skipped";
  try {
    let settled = false;
    const result = await Promise.race([
      worker
        .terminate()
        .then(() => {
          settled = true;
          return "terminated" as const;
        })
        .catch(() => {
          settled = true;
          return "failed" as const;
        }),
      new Promise<"timeout">((resolve) => {
        setTimeout(() => resolve("timeout"), timeoutMs);
      }),
    ]);
    if (result === "timeout" && !settled) {
      void Promise.resolve(worker.terminate()).catch(() => undefined);
    }
    return result;
  } catch {
    return "failed";
  }
}

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

function canvasToJpeg(canvas: HTMLCanvasElement, quality = 0.92): string {
  return canvas.toDataURL("image/jpeg", quality);
}

function applyGrayscaleContrast(ctx: CanvasRenderingContext2D, width: number, height: number, mode: "soft" | "hard"): void {
  try {
    const imageData = ctx.getImageData(0, 0, width, height);
    const data = imageData.data;
    for (let i = 0; i < data.length; i += 4) {
      const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
      let value: number;
      if (mode === "hard") {
        // High-contrast threshold for MRZ OCR on shadowed phone photos.
        value = gray < 150 ? 0 : 255;
      } else {
        value = gray < 140 ? Math.max(0, gray * 0.85) : Math.min(255, gray * 1.08);
      }
      data[i] = value;
      data[i + 1] = value;
      data[i + 2] = value;
    }
    ctx.putImageData(imageData, 0, 0);
  } catch {
    // Some browsers block getImageData; keep the drawn image.
  }
}

/**
 * Build OCR image variants without uploading or persisting the passport photo.
 * Pass 1: normalized full page · Pass 2: bottom MRZ crop · Pass 3: high-contrast MRZ crop.
 */
export async function preprocessPassportImageVariants(file: File): Promise<PassportImageVariants> {
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
  let width = image.naturalWidth || image.width;
  let height = image.naturalHeight || image.height;
  if (width < MIN_DIMENSION || height < MIN_DIMENSION) {
    throw new Error("Image is too small. Capture the full passport data page more clearly.");
  }

  // Prefer landscape MRZ orientation: if portrait and taller than wide, keep as-is
  // (phone photos of data pages are often portrait and still readable).
  const scale = Math.min(1, MAX_DIMENSION / Math.max(width, height));
  const targetW = Math.max(1, Math.round(width * scale));
  const targetH = Math.max(1, Math.round(height * scale));

  const fullCanvas = document.createElement("canvas");
  fullCanvas.width = targetW;
  fullCanvas.height = targetH;
  const fullCtx = fullCanvas.getContext("2d", { willReadFrequently: true });
  if (!fullCtx) {
    throw new Error("This device cannot process passport images.");
  }
  fullCtx.fillStyle = "#ffffff";
  fullCtx.fillRect(0, 0, targetW, targetH);
  fullCtx.drawImage(image, 0, 0, targetW, targetH);
  applyGrayscaleContrast(fullCtx, targetW, targetH, "soft");
  const fullPage = canvasToJpeg(fullCanvas);

  // Bottom ~38% of the page typically contains the TD3 MRZ.
  const cropTop = Math.floor(targetH * 0.58);
  const cropH = Math.max(80, targetH - cropTop);
  const mrzCanvas = document.createElement("canvas");
  mrzCanvas.width = targetW;
  mrzCanvas.height = cropH;
  const mrzCtx = mrzCanvas.getContext("2d", { willReadFrequently: true });
  if (!mrzCtx) {
    throw new Error("This device cannot process passport images.");
  }
  mrzCtx.fillStyle = "#ffffff";
  mrzCtx.fillRect(0, 0, targetW, cropH);
  mrzCtx.drawImage(fullCanvas, 0, cropTop, targetW, cropH, 0, 0, targetW, cropH);
  applyGrayscaleContrast(mrzCtx, targetW, cropH, "soft");
  const mrzCrop = canvasToJpeg(mrzCanvas);

  const hardCanvas = document.createElement("canvas");
  hardCanvas.width = targetW;
  hardCanvas.height = cropH;
  const hardCtx = hardCanvas.getContext("2d", { willReadFrequently: true });
  if (!hardCtx) {
    throw new Error("This device cannot process passport images.");
  }
  hardCtx.drawImage(mrzCanvas, 0, 0);
  applyGrayscaleContrast(hardCtx, targetW, cropH, "hard");
  const mrzHighContrast = canvasToJpeg(hardCanvas);

  return {
    fullPage,
    mrzCrop,
    mrzHighContrast,
    width: targetW,
    height: targetH,
  };
}

/** @deprecated Prefer preprocessPassportImageVariants for multi-pass OCR. */
export async function preprocessPassportImage(file: File): Promise<{ dataUrl: string; width: number; height: number }> {
  const variants = await preprocessPassportImageVariants(file);
  return { dataUrl: variants.fullPage, width: variants.width, height: variants.height };
}

function isStrongMrzParse(result: MrzParseResult): boolean {
  return result.ok === true && result.checkDigitsValid === true;
}

/**
 * CLIENT_SIDE document scan. Images never leave the browser and are never persisted.
 * Multi-pass OCR stops after the first valid TD3 parse with check digits.
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
  let worker: LocalOcrWorker | null = null;

  try {
    assertNotAborted(options.signal);
    options.onProgress?.({ status: "preparing", progress: 0.05 });

    const variants = await withTimeout(
      preprocessPassportImageVariants(source.file),
      Math.min(12_000, timeoutMs),
      options.signal,
    );
    options.onProgress?.({ status: "loading_ocr", progress: 0.12 });

    const { createWorker } = await import("tesseract.js");
    assertNotAborted(options.signal);

    worker = (await withTimeout(
      createWorker("eng", 1, {
        workerPath: `${TESSERACT_ASSET_PATH}/worker.min.js`,
        corePath: `${TESSERACT_ASSET_PATH}/tesseract-core-simd-lstm.wasm.js`,
        langPath: TESSERACT_ASSET_PATH,
        gzip: true,
        logger: (message: { status?: string; progress?: number }) => {
          if (typeof message.progress === "number") {
            options.onProgress?.({
              status: message.status ?? "recognizing",
              progress: Math.min(0.92, 0.15 + message.progress * 0.7),
            });
          }
        },
      }),
      timeoutMs,
      options.signal,
    )) as unknown as LocalOcrWorker;

    const passes: Array<{ label: string; dataUrl: string; psm: string; progressAt: number }> = [
      { label: "full", dataUrl: variants.fullPage, psm: "6", progressAt: 0.35 },
      { label: "mrz", dataUrl: variants.mrzCrop, psm: "6", progressAt: 0.55 },
      { label: "mrz_hard", dataUrl: variants.mrzHighContrast, psm: "7", progressAt: 0.75 },
    ];

    let bestPartial: MrzParseResult | null = null;

    for (const pass of passes) {
      assertNotAborted(options.signal);
      if (worker.setParameters) {
        await worker.setParameters({
          tessedit_pageseg_mode: pass.psm,
          preserve_interword_spaces: "1",
          tessedit_char_whitelist: "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<",
        });
      }
      options.onProgress?.({ status: "recognizing", progress: pass.progressAt });
      const remaining = Math.max(8_000, timeoutMs - 5_000);
      const recognized = await withTimeout(worker.recognize(pass.dataUrl), remaining, options.signal);
      const parsed = parseTd3Mrz(recognized.data.text);
      if (isStrongMrzParse(parsed)) {
        options.onProgress?.({ status: "parsing", progress: 0.95 });
        options.onProgress?.({ status: "done", progress: 1 });
        return parsed;
      }
      if (parsed.ok || Object.keys(parsed.fields).length > Object.keys(bestPartial?.fields ?? {}).length) {
        bestPartial = parsed;
      }
    }

    options.onProgress?.({ status: "parsing", progress: 0.95 });
    const result = bestPartial ?? fail(CUSTOMER_FAILURE);
    if (!result.ok) {
      result.warnings = [CUSTOMER_FAILURE];
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
    return fail(CUSTOMER_FAILURE);
  } finally {
    await terminateWorkerSafely(worker);
    worker = null;
  }
}
