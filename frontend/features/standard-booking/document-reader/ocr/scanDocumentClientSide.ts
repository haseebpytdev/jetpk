import { parseTd3Mrz, type MrzParseResult } from "../mrz/parseMrz";

export type DocumentScanSource = {
  kind: "text" | "image";
  text?: string;
  file?: File;
};

/**
 * CLIENT_SIDE document scan. Images never leave the browser.
 * tesseract.js is loaded only when an image is provided (dynamic import).
 */
export async function scanDocumentClientSide(source: DocumentScanSource): Promise<MrzParseResult> {
  if (source.kind === "text") {
    return parseTd3Mrz(source.text ?? "");
  }

  if (!source.file) {
    return {
      ok: false,
      fields: {},
      confidence: [],
      warnings: ["No document image selected."],
      checkDigitsValid: false,
      rawLines: [],
    };
  }

  if (source.file.type.startsWith("text/") || /\.txt$/i.test(source.file.name)) {
    const text = await source.file.text();
    return parseTd3Mrz(text);
  }

  const objectUrl = URL.createObjectURL(source.file);
  try {
    const { createWorker } = await import("tesseract.js");
    const worker = await createWorker("eng");
    try {
      const {
        data: { text },
      } = await worker.recognize(objectUrl);
      const result = parseTd3Mrz(text);
      if (!result.ok && result.warnings.length === 0) {
        result.warnings.push("OCR finished but no MRZ lines were detected. Try pasting the MRZ or a clearer scan.");
      } else if (!result.ok) {
        result.warnings.push("OCR could not read a complete MRZ. You can paste the two MRZ lines manually.");
      }
      return result;
    } finally {
      await worker.terminate();
    }
  } catch {
    return {
      ok: false,
      fields: {},
      confidence: [],
      warnings: [
        "Client-side OCR failed to load. Paste the two MRZ lines from the passport instead — images are never uploaded.",
      ],
      checkDigitsValid: false,
      rawLines: [],
    };
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}
