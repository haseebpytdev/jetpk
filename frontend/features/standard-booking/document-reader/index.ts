export { DocumentReader } from "./components/DocumentReader";
export { parseTd3Mrz, mrzCheckDigit, verifyCheckDigit, mrzDateToIso, alpha3ToAlpha2 } from "./mrz/parseMrz";
export type { MrzExtractedFields, MrzParseResult } from "./mrz/parseMrz";
export { SYNTHETIC_MRZ_FIXTURES } from "./mrz/fixtures";
export { planExtractedFieldMerge, applyConfirmedExtraction } from "./applyExtractedFields";
export { scanDocumentClientSide } from "./ocr/scanDocumentClientSide";
