#!/usr/bin/env node
/**
 * JP-PUBLIC-NEXT-THEME-03C reference geometry contract loader + validator.
 * Reference landmarks come ONLY from the reviewed contract file.
 */
import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");

export const CONTRACT_PATH = path.join(
  frontendRoot,
  "tests/visual-audit/jp-public-next-theme-03c-reference-geometry.json",
);

export const REGION_ORDER = [
  "header",
  "hero",
  "search",
  "benefits",
  "discover",
  "destinations",
  "offers",
  "why",
  "support",
  "inspiration",
  "footer",
];

export function loadReferenceContract(contractPath = CONTRACT_PATH) {
  const contract = JSON.parse(readFileSync(contractPath, "utf8"));
  validateReferenceContract(contract);
  return contract;
}

export function validateReferenceContract(contract) {
  const errors = [];
  const vw = contract.viewport?.width ?? 1122;
  const vh = contract.viewport?.height ?? 1330;
  const landmarks = contract.landmarks ?? {};

  for (const name of REGION_ORDER) {
    const box = landmarks[name];
    if (!box) {
      errors.push(`Missing landmark: ${name}`);
      continue;
    }
    for (const key of ["x", "y", "width", "height", "right", "bottom"]) {
      if (typeof box[key] !== "number") {
        errors.push(`${name}: missing or invalid ${key}`);
      }
    }
    if (box.x < 0) errors.push(`${name}: x < 0`);
    if (box.y < 0) errors.push(`${name}: y < 0`);
    if (box.width <= 0) errors.push(`${name}: width <= 0`);
    if (box.height <= 0) errors.push(`${name}: height <= 0`);
    if (box.right > vw) errors.push(`${name}: right ${box.right} > viewport width ${vw}`);
    if (box.bottom > vh) errors.push(`${name}: bottom ${box.bottom} > viewport height ${vh}`);
    if (box.right !== box.x + box.width) {
      errors.push(`${name}: right !== x + width (${box.right} !== ${box.x} + ${box.width})`);
    }
    if (box.bottom !== box.y + box.height) {
      errors.push(`${name}: bottom !== y + height (${box.bottom} !== ${box.y} + ${box.height})`);
    }
  }

  let prevBottom = -1;
  for (const name of REGION_ORDER) {
    const box = landmarks[name];
    if (!box) continue;
    if (box.y < prevBottom - 60) {
      errors.push(`${name}: y=${box.y} overlaps previous section bottom=${prevBottom} (search overlap exempt)`);
    }
    if (name !== "search" && box.y < prevBottom) {
      errors.push(`${name}: section order violation — y=${box.y} before prev bottom=${prevBottom}`);
    }
    prevBottom = Math.max(prevBottom, box.bottom);
  }

  if (contract.pageHeight !== vh) {
    errors.push(`pageHeight ${contract.pageHeight} !== viewport height ${vh}`);
  }

  if (errors.length > 0) {
    throw new Error(`Reference contract validation failed:\n${errors.map((e) => `  - ${e}`).join("\n")}`);
  }

  return true;
}

export function getTolerance(region, contract) {
  const t = contract.tolerances ?? {};
  if (region === "pageHeight") return t.pageHeight ?? 8;
  if (region === "footer") return t.footer ?? 8;
  if (region === "search") return t.search ?? 8;
  if (region === "header" || region === "hero") return t.headerHero ?? 8;
  return t.remaining ?? 12;
}

export function withinTolerance(refVal, implVal, tolerance) {
  if (refVal == null || implVal == null) return false;
  return Math.abs(implVal - refVal) <= tolerance;
}

export function evaluateRegion(region, refBox, implBox, contract) {
  const tolerance = getTolerance(region, contract);
  const dims = ["x", "y", "width", "height"];
  const delta = {};
  const checks = {};
  let pass = implBox != null;

  if (!implBox) {
    return { region, reference: refBox, implementation: null, delta: null, tolerance, checks: {}, pass: false, reason: "missing landmark" };
  }

  for (const dim of dims) {
    delta[dim] = implBox[dim] - refBox[dim];
    checks[dim] = withinTolerance(refBox[dim], implBox[dim], tolerance);
    if (!checks[dim]) pass = false;
  }

  return { region, reference: refBox, implementation: implBox, delta, tolerance, checks, pass };
}

export function evaluateGeometry(contract, implGeom) {
  const rows = [];
  let allPass = true;
  const failures = [];

  for (const name of REGION_ORDER) {
    const refBox = contract.landmarks[name];
    const implBox = implGeom.landmarks?.[name] ?? null;
    const row = evaluateRegion(name, refBox, implBox, contract);
    rows.push(row);
    if (!row.pass) {
      allPass = false;
      failures.push(`${name}: ${row.reason ?? "dimension out of tolerance"}`);
    }
  }

  const pageTolerance = getTolerance("pageHeight", contract);
  const pageHeight = implGeom.pageHeight ?? null;
  const pageHeightPass =
    pageHeight != null && withinTolerance(contract.pageHeight, pageHeight, pageTolerance);
  rows.push({
    region: "pageHeight",
    reference: { height: contract.pageHeight },
    implementation: pageHeight != null ? { height: pageHeight } : null,
    delta: pageHeight != null ? { height: pageHeight - contract.pageHeight } : null,
    tolerance: pageTolerance,
    checks: { height: pageHeightPass },
    pass: pageHeightPass,
  });
  if (!pageHeightPass) {
    allPass = false;
    failures.push(`pageHeight: ${pageHeight} not within ${contract.pageHeight}±${pageTolerance}`);
  }

  const footerRef = contract.landmarks.footer;
  const footerImpl = implGeom.landmarks?.footer;
  const footerTolerance = getTolerance("footer", contract);
  if (footerImpl) {
    const footerTopPass = withinTolerance(footerRef.y, footerImpl.y, footerTolerance);
    const footerBottomPass = withinTolerance(footerRef.bottom, footerImpl.bottom, footerTolerance);
    if (!footerTopPass) {
      allPass = false;
      failures.push(`footer top: y=${footerImpl.y} not within ${footerRef.y}±${footerTolerance}`);
    }
    if (!footerBottomPass) {
      allPass = false;
      failures.push(`footer bottom: ${footerImpl.bottom} not within ${footerRef.bottom}±${footerTolerance}`);
    }
  }

  return { rows, structuralPass: allPass, failures };
}

export function evaluateOverflowAudit(audit, viewportWidth = 1122) {
  const failures = [];
  let pass = true;

  if (audit.scrollWidth > viewportWidth + 1) {
    pass = false;
    failures.push(`document scrollWidth ${audit.scrollWidth} > viewport ${viewportWidth}`);
  }

  for (const item of audit.landmarks ?? []) {
    if (item.left < -1) {
      pass = false;
      failures.push(`${item.region}: left=${item.left} < -1`);
    }
    if (item.right > viewportWidth + 1) {
      pass = false;
      failures.push(`${item.region}: right=${item.right} > ${viewportWidth + 1}`);
    }
  }

  return { pass, failures };
}

export function evaluateClippingAudit(audit) {
  const failures = [];
  let pass = true;

  for (const item of audit.sections ?? []) {
    if (item.scrollHeight > item.clientHeight + 2) {
      pass = false;
      failures.push(`${item.region}: scrollHeight ${item.scrollHeight} > clientHeight ${item.clientHeight}`);
    }
    if (item.hiddenRequired) {
      pass = false;
      failures.push(`${item.region}: required content hidden via display:none`);
    }
  }

  return { pass, failures };
}

export const GAP_PAIRS = [
  { from: "header", to: "hero", tolerance: 8 },
  { from: "hero", to: "search", tolerance: 8 },
  { from: "search", to: "benefits", tolerance: 12 },
  { from: "benefits", to: "discover", tolerance: 12 },
  { from: "discover", to: "destinations", tolerance: 12 },
  { from: "destinations", to: "offers", tolerance: 12 },
  { from: "offers", to: "why", tolerance: 12 },
  { from: "why", to: "support", tolerance: 12 },
  { from: "support", to: "inspiration", tolerance: 12 },
  { from: "inspiration", to: "footer", tolerance: 8 },
];

export function computeReferenceGap(contract, from, to) {
  const fromBox = contract.landmarks[from];
  const toBox = contract.landmarks[to];
  if (!fromBox || !toBox) return null;
  return toBox.y - fromBox.bottom;
}

export function evaluateGapAudit(contract, implGeom) {
  const rows = [];
  const failures = [];
  let pass = true;

  for (const pair of GAP_PAIRS) {
    const refGap = computeReferenceGap(contract, pair.from, pair.to);
    const fromImpl = implGeom.landmarks?.[pair.from];
    const toImpl = implGeom.landmarks?.[pair.to];
    let implGap = null;
    if (fromImpl && toImpl) {
      implGap = toImpl.y - fromImpl.bottom;
    }
    const delta = implGap != null && refGap != null ? implGap - refGap : null;
    const rowPass =
      implGap != null && refGap != null && Math.abs(delta) <= pair.tolerance;
    if (!rowPass) {
      pass = false;
      failures.push(
        `${pair.from}→${pair.to}: impl gap ${implGap} vs ref ${refGap} (Δ${delta}, tol ±${pair.tolerance})`,
      );
    }
    rows.push({
      from: pair.from,
      to: pair.to,
      referenceGap: refGap,
      implementationGap: implGap,
      delta,
      tolerance: pair.tolerance,
      pass: rowPass,
    });
  }

  return { rows, pass, failures };
}

export function evaluateTailIntegrity(implGeom) {
  const footer = implGeom.landmarks?.footer;
  const docScrollHeight = implGeom.pageHeight ?? null;
  const bodyScrollHeight = implGeom.bodyScrollHeight ?? null;
  const footerBottom = footer?.bottom ?? null;
  const emptyBelowFooter =
    docScrollHeight != null && footerBottom != null ? docScrollHeight - footerBottom : null;
  const docFooterDelta =
    docScrollHeight != null && footerBottom != null
      ? Math.abs(docScrollHeight - footerBottom)
      : null;

  const failures = [];
  let pass = true;

  if (emptyBelowFooter != null && emptyBelowFooter > 8) {
    pass = false;
    failures.push(`empty space below footer: ${emptyBelowFooter}px > 8px`);
  }
  if (docFooterDelta != null && docFooterDelta > 8) {
    pass = false;
    failures.push(`|documentScrollHeight - footerBottom| = ${docFooterDelta}px > 8px`);
  }

  return {
    pass,
    failures,
    bodyScrollHeight,
    documentScrollHeight: docScrollHeight,
    footerTop: footer?.y ?? null,
    footerHeight: footer?.height ?? null,
    footerBottom,
    emptyBelowFooter,
    documentFooterDelta: docFooterDelta,
  };
}
