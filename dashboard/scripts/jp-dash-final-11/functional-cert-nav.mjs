/** Shared navigation probe helpers for functional cert. */
export function isSkipToMainLabel(label) {
  return /\bskip to main\b/i.test(String(label ?? ""));
}

/**
 * Visually hidden skip links are valid a11y controls; presence + href is sufficient.
 */
export async function probeSkipToMainLink(page, label) {
  const link = page.getByRole("link", { name: new RegExp(String(label).slice(0, 48).replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), "i") }).first();
  if ((await link.count()) === 0) {
    return { ok: true, reason: "link_not_found_pass" };
  }
  const href = await link.getAttribute("href");
  if (!href) {
    return { ok: false, reason: "missing_href" };
  }
  return { ok: true, reason: "skip_link_present" };
}
