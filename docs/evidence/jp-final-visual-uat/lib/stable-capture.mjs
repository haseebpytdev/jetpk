/**
 * CORRECTION-08 shared visual evidence helpers.
 * Stable-state waits, progressive scroll, positive assertions, FAB overlap.
 */
export const REJECT_TEXT_PATTERNS = [
  /Loading overview/i,
  /Loading navigation/i,
  /Loading\.\.\./i,
  /Loading payment/i,
  /Something went wrong/i,
  /Unable to load data/i,
  /OV-UNKNOWN/i,
  /Something went wrong while loading data/i,
  /Payment unavailable/i,
];

export const HOMEPAGE_SECTIONS = [
  { key: "HEADER", testIds: [], selectors: ["header", "[data-testid='public-header']"], text: null },
  { key: "HERO", testIds: [], selectors: ["[data-testid='home-hero']", "section[aria-label*='hero' i]", ".jp-home-hero"], text: null },
  { key: "FLIGHT_SEARCH", testIds: ["flight-search", "home-flight-search"], selectors: ["form[action*='flight']", "[data-testid*='flight-search']"], text: /Search flights|From|To/i },
  { key: "GROUPS", testIds: ["home-groups", "group-ticketing"], selectors: ["[data-testid*='group']"], text: /Group/i },
  { key: "TRUST_PROOF", testIds: ["home-trust"], selectors: ["[data-testid*='trust']"], text: /Trusted|Travelers|Bookings/i },
  { key: "TRENDING_ROUTES", testIds: ["trending-routes"], selectors: ["[data-testid*='trending']"], text: /Trending/i },
  { key: "DESTINATIONS_ON_THE_RISE", testIds: ["destinations-on-the-rise"], selectors: ["[data-testid*='destination']"], text: /Destinations on the rise|Popular destinations/i },
  { key: "FEATURED_DEALS", testIds: ["featured-deals"], selectors: ["[data-testid*='featured-deal']", "[data-testid*='deal']"], text: /Featured deals|Deals/i },
  { key: "WHY_JETPAKISTAN", testIds: ["why-jetpakistan"], selectors: ["[data-testid*='why']"], text: /Why JetPakistan|Why choose/i },
  { key: "SUPPORT_CTA", testIds: ["support-cta", "home-support"], selectors: ["[data-testid*='support']", "a[href='/support']"], text: /Support|Need help/i },
  { key: "FOOTER", testIds: ["public-footer"], selectors: ["footer"], text: null },
];

/**
 * Progressive scroll to trigger IntersectionObserver / lazy reveal, then return to top.
 */
export async function stabilizeFullPage(page, { settleMs = 250 } = {}) {
  await page
    .waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", { timeout: 15000 })
    .catch(() => {});
  await page.waitForLoadState("networkidle", { timeout: 15000 }).catch(() => {});

  await page.evaluate(async ({ settleMs: settle }) => {
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const doc = document.documentElement;
    const body = document.body;
    const height = Math.max(doc.scrollHeight, body?.scrollHeight || 0);
    const step = Math.max(320, Math.floor(window.innerHeight * 0.85));
    for (let y = 0; y < height + step; y += step) {
      window.scrollTo(0, y);
      await sleep(settle);
    }
    // Final settle at bottom then top
    window.scrollTo(0, height);
    await sleep(settle * 2);
    // Wait for lazy images in viewport path
    const imgs = Array.from(document.images || []);
    await Promise.all(
      imgs.map((img) => {
        if (img.complete && img.naturalWidth > 0) return Promise.resolve();
        return new Promise((resolve) => {
          const done = () => resolve();
          img.addEventListener("load", done, { once: true });
          img.addEventListener("error", done, { once: true });
          setTimeout(done, 4000);
        });
      }),
    );
    window.scrollTo(0, 0);
    await sleep(settle);
  }, { settleMs });
}

export async function waitForStableText(page, { timeout = 25000, allowErrorState = false } = {}) {
  const started = Date.now();
  let lastBody = "";
  while (Date.now() - started < timeout) {
    const probe = await page.evaluate((patterns) => {
      const body = (document.body?.innerText || "").replace(/\s+/g, " ").trim();
      const hits = [];
      for (const src of patterns) {
        const re = new RegExp(src.source, src.flags);
        if (re.test(body)) hits.push(src.source);
      }
      return { body: body.slice(0, 4000), hits };
    }, REJECT_TEXT_PATTERNS.map((r) => ({ source: r.source, flags: r.flags })));

    lastBody = probe.body;
    if (!allowErrorState && probe.hits.length) {
      await page.waitForTimeout(400);
      // If still present after a short wait, continue until timeout then fail
      const still = await page.evaluate((patterns) => {
        const body = (document.body?.innerText || "").replace(/\s+/g, " ");
        return patterns
          .map((p) => new RegExp(p.source, p.flags))
          .filter((re) => re.test(body))
          .map((re) => re.source);
      }, REJECT_TEXT_PATTERNS.map((r) => ({ source: r.source, flags: r.flags })));
      if (still.length === 0) continue;
      // keep waiting — maybe still resolving
      await page.waitForTimeout(600);
      continue;
    }

    // No reject patterns — check we're not empty shell
    if ((probe.body || "").length > 40) {
      return { ok: true, body: probe.body, rejectHits: [] };
    }
    await page.waitForTimeout(400);
  }

  const finalHits = await page.evaluate((patterns) => {
    const body = (document.body?.innerText || "").replace(/\s+/g, " ");
    return patterns
      .map((p) => new RegExp(p.source, p.flags))
      .filter((re) => re.test(body))
      .map((re) => re.source);
  }, REJECT_TEXT_PATTERNS.map((r) => ({ source: r.source, flags: r.flags })));

  return {
    ok: false,
    body: lastBody,
    rejectHits: finalHits,
    reason: finalHits.length
      ? `UNSTABLE_OR_ERROR: ${finalHits.join(",")}`
      : "EMPTY_OR_TIMEOUT",
  };
}

export async function assertNoRejectState(page, { allowErrorState = false } = {}) {
  const body = await page.evaluate(() => (document.body?.innerText || "").replace(/\s+/g, " "));
  if (allowErrorState) return { ok: true, body, rejectHits: [] };
  const rejectHits = REJECT_TEXT_PATTERNS.filter((re) => re.test(body)).map((re) => re.source);
  return {
    ok: rejectHits.length === 0,
    body: body.slice(0, 4000),
    rejectHits,
    reason: rejectHits.length ? `REJECT_STATE: ${rejectHits.join(",")}` : null,
  };
}

export async function assertHomepageSections(page) {
  const result = await page.evaluate((sections) => {
    const body = (document.body?.innerText || "").replace(/\s+/g, " ");
    const missing = [];
    const present = [];
    for (const s of sections) {
      let found = false;
      for (const id of s.testIds || []) {
        if (document.querySelector(`[data-testid='${id}']`)) {
          found = true;
          break;
        }
      }
      if (!found) {
        for (const sel of s.selectors || []) {
          try {
            if (document.querySelector(sel)) {
              found = true;
              break;
            }
          } catch {
            /* ignore invalid */
          }
        }
      }
      if (!found && s.text) {
        const re = new RegExp(s.text.source || s.text, s.text.flags || "i");
        if (re.test(body)) found = true;
      }
      if (found) present.push(s.key);
      else missing.push(s.key);
    }
    return { present, missing, MISSING_APPROVED_HOMEPAGE_SECTIONS: missing.length };
  }, HOMEPAGE_SECTIONS.map((s) => ({
    ...s,
    text: s.text ? { source: s.text.source, flags: s.text.flags } : null,
  })));
  return result;
}

export async function assertRouteMedia(page) {
  return page.evaluate(() => {
    const blank = [];
    const imgs = Array.from(
      document.querySelectorAll(
        "[data-testid*='trending'] img, [data-testid*='destination'] img, [data-testid*='deal'] img, [data-testid*='featured'] img, section img",
      ),
    );
    let incomplete = 0;
    for (const img of imgs) {
      const r = img.getBoundingClientRect();
      if (r.width < 8 || r.height < 8) continue;
      if (!img.complete || img.naturalWidth === 0) {
        incomplete += 1;
        blank.push({
          src: (img.getAttribute("src") || "").slice(0, 120),
          naturalWidth: img.naturalWidth,
          complete: img.complete,
        });
      }
    }
    return {
      IMG_COMPLETE: incomplete === 0 ? "YES" : "NO",
      BLANK_ROUTE_MEDIA: blank.length,
      blanks: blank.slice(0, 12),
      scanned: imgs.length,
    };
  });
}

/**
 * Meaningful FAB overlap: interactive + card content + marketing text with non-trivial area.
 */
export async function measureFabOverlap(page) {
  return page.evaluate(() => {
    const body = document.body;
    const fab =
      document.querySelector("[data-testid='ask-jetpakistan-fab']") ||
      document.querySelector(".jp-ask-fab, [data-jp-ask-fab]");
    const dock = document.querySelector(".jp-public-fab-dock");
    const controls = [fab, dock].filter(Boolean);
    if (!controls.length) {
      return {
        fabOverlap: 0,
        FAB_MEANINGFUL_CONTENT_OVERLAP: 0,
        FAB_CARD_CONTENT_OVERLAP: 0,
        FAB_INTERACTIVE_OVERLAP: 0,
        FAB_CTA_OVERLAP: 0,
        fabHits: [],
      };
    }

    const ignoreClosest =
      "[data-testid='ask-jetpakistan-fab'], .jp-public-fab-dock, [data-jp-ask-open], [data-testid^='fab-'], [data-testid^='ask-']";

    const candidates = body.querySelectorAll(
      "a[href], button, input, select, textarea, label, [role='button'], [role='link'], [role='textbox'], h1, h2, h3, p, li, article, [class*='card' i], [data-testid*='card'], [data-testid*='deal'], [data-testid*='group']",
    );

    let meaningful = 0;
    let card = 0;
    let interactive = 0;
    let cta = 0;
    const hits = [];

    function overlaps(a, b) {
      const ix = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left));
      const iy = Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
      return ix * iy;
    }

    for (const el of candidates) {
      if (controls.some((c) => c === el || c.contains(el) || el.contains(c))) continue;
      if (el.closest(ignoreClosest)) continue;
      const testid = el.getAttribute("data-testid") || "";
      if (testid.startsWith("fab-") || testid.startsWith("ask-")) continue;
      const style = window.getComputedStyle(el);
      if (style.visibility === "hidden" || style.display === "none" || Number(style.opacity) === 0) {
        continue;
      }
      const r = el.getBoundingClientRect();
      if (r.width <= 0 || r.height <= 0) continue;

      let area = 0;
      for (const c of controls) {
        area = Math.max(area, overlaps(r, c.getBoundingClientRect()));
      }
      // Require meaningful obstruction (≥48px²) — not a 1px kiss.
      if (area < 48) continue;

      const tag = el.tagName.toLowerCase();
      const role = el.getAttribute("role") || tag;
      const isInteractive =
        ["a", "button", "input", "select", "textarea", "label"].includes(tag) ||
        ["button", "link", "textbox"].includes(role);
      const isCard =
        /card/i.test(el.className?.toString?.() || "") ||
        /card|deal|group/i.test(testid) ||
        tag === "article";
      const text = (el.textContent || "").trim().slice(0, 60);
      const isCta =
        /submit|continue|search|book|pay|register|sign in|log in/i.test(text) ||
        /cta|submit|continue/i.test(testid);

      meaningful += 1;
      if (isCard) card += 1;
      if (isInteractive) interactive += 1;
      if (isCta) cta += 1;
      hits.push({
        tag,
        role,
        interactive: isInteractive,
        card: isCard,
        cta: isCta,
        text,
        area: Math.round(area),
        testid,
      });
    }

    return {
      fabOverlap: meaningful,
      FAB_MEANINGFUL_CONTENT_OVERLAP: meaningful,
      FAB_CARD_CONTENT_OVERLAP: card,
      FAB_INTERACTIVE_OVERLAP: interactive,
      FAB_CTA_OVERLAP: cta,
      fabHits: hits.slice(0, 10),
    };
  });
}

export async function measureOverflow(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const scrollW = Math.max(doc.scrollWidth, body?.scrollWidth || 0);
    return { overflowX: scrollW - vw > 2 ? Math.round(scrollW - vw) : 0, path: location.pathname };
  });
}

export async function assertStyledApp(page) {
  return page.evaluate(() => {
    const stylesheets = Array.from(document.styleSheets || []).length;
    const linkCss = document.querySelectorAll('link[rel="stylesheet"]').length;
    const root = getComputedStyle(document.documentElement);
    const hasBrand =
      Boolean(root.getPropertyValue("--jp-primary")?.trim()) ||
      Boolean(root.getPropertyValue("--color-primary")?.trim()) ||
      Boolean(document.querySelector("[class*='jp-'], [class*='text-jp-']"));
    const nav =
      document.querySelector("aside nav, nav[aria-label*='admin' i], [data-testid*='sidebar'], [data-testid*='admin-nav']") ||
      document.querySelector("aside");
    return {
      stylesheets,
      linkCss,
      hasBrand,
      hasNav: Boolean(nav),
      ADMIN_DASHBOARD_STYLED: hasBrand && (stylesheets > 0 || linkCss > 0) ? "YES" : "NO",
    };
  });
}

export function routePositiveSpec(routeKey) {
  const specs = {
    home: {
      requireText: [/JetPakistan/i],
      requireSelectors: ["header", "footer"],
    },
    login: {
      requireText: [/Sign in|Log in|Email/i],
      requireSelectors: ["form", "input[type='email'], input[name='email']"],
    },
    register: {
      requireText: [/Register|Create account|Sign up/i],
      requireSelectors: ["form"],
    },
    "forgot-password": {
      requireText: [/Forgot|Reset|password/i],
      requireSelectors: ["form"],
    },
    "groups-search": {
      requireText: [/Group/i],
      requireSelectors: ["form, [data-testid*='group']"],
    },
    "customer-dashboard": {
      requireText: [/Dashboard|Overview|My bookings|Welcome/i],
      forbidText: [/Loading overview/i, /Something went wrong/i],
      requireSelectors: ["h1, [data-testid*='dashboard']"],
    },
    "agent-dashboard": {
      requireText: [/Dashboard|Overview|Agent/i],
      forbidText: [/Loading overview/i, /Loading navigation/i],
      requireSelectors: ["h1, nav, [data-testid*='dashboard']"],
    },
    "admin-dashboard": {
      requireText: [/Dashboard|Overview|Admin/i],
      forbidText: [/Unable to load data/i, /OV-UNKNOWN/i, /Something went wrong while loading data/i],
      requireSelectors: ["aside, nav, h1"],
      requireStyled: true,
    },
    "group-payment": {
      requireText: [/Complete payment|Submit payment for review|Manual payment only/i],
      forbidText: [/Choose how you paid/i, /Loading payment/i],
      requireSelectors: [
        "[data-testid='group-payment-submit']",
        "[data-testid^='group-payment-method-']",
        "#group-booking-summary-heading, [id='group-booking-summary-heading']",
      ],
      minMethodCards: 3,
    },
  };
  return specs[routeKey] || { requireText: [], requireSelectors: [] };
}

export async function assertPositiveRoute(page, routeKey) {
  const spec = routePositiveSpec(routeKey);
  return page.evaluate((specIn) => {
    const body = (document.body?.innerText || "").replace(/\s+/g, " ");
    const fails = [];
    for (const t of specIn.requireText || []) {
      const re = new RegExp(t.source, t.flags || "i");
      if (!re.test(body)) fails.push(`missingText:${t.source}`);
    }
    for (const t of specIn.forbidText || []) {
      const re = new RegExp(t.source, t.flags || "i");
      if (re.test(body)) fails.push(`forbidText:${t.source}`);
    }
    for (const sel of specIn.requireSelectors || []) {
      try {
        if (!document.querySelector(sel)) fails.push(`missingSelector:${sel}`);
      } catch {
        fails.push(`badSelector:${sel}`);
      }
    }
    if (specIn.minMethodCards) {
      const n = document.querySelectorAll("[data-testid^='group-payment-method-']").length;
      if (n < specIn.minMethodCards) fails.push(`methodCards:${n}<${specIn.minMethodCards}`);
    }
    if (specIn.requireStyled) {
      const linkCss = document.querySelectorAll('link[rel="stylesheet"]').length;
      const hasJp = Boolean(document.querySelector("[class*='jp-'], [class*='text-jp-'], aside"));
      if (linkCss === 0 && !hasJp) fails.push("unstyled");
    }
    // Signature for group payment source currency
    const signature = {
      hasManualOnly: /Manual payment only/i.test(body),
      hasChooseHow: /Choose how you paid/i.test(body),
      hasSubmitReview: /Submit payment for review/i.test(body),
      methodCards: document.querySelectorAll("[data-testid^='group-payment-method-']").length,
    };
    return {
      ok: fails.length === 0,
      fails,
      signature,
      h1: document.querySelector("h1")?.textContent?.trim()?.slice(0, 80) || "",
    };
  }, {
    ...spec,
    requireText: (spec.requireText || []).map((r) => ({ source: r.source, flags: r.flags })),
    forbidText: (spec.forbidText || []).map((r) => ({ source: r.source, flags: r.flags })),
  });
}

export async function assertGroupPaymentVisual(page) {
  return page.evaluate(() => {
    const vw = window.innerWidth;
    const cards = Array.from(document.querySelectorAll("[data-testid^='group-payment-method-']"));
    let separation = 0;
    let cardPass = 0;
    for (const card of cards) {
      const style = getComputedStyle(card);
      const border = parseFloat(style.borderTopWidth || "0") + parseFloat(style.borderBottomWidth || "0");
      const hasBorder = border >= 1 || style.boxShadow !== "none";
      const radio = card.querySelector("input[type='radio']");
      const title = (card.querySelector("span, p, strong")?.textContent || "").trim();
      const helpEls = card.querySelectorAll("span, p");
      const help = helpEls.length > 1;
      if (radio && title && hasBorder) cardPass += 1;
      if (hasBorder) separation += 1;
    }
    const cta =
      document.querySelector("[data-testid='group-payment-submit']") ||
      Array.from(document.querySelectorAll("button")).find((b) => /submit payment/i.test(b.textContent || ""));
    let ctaVisual = "FAIL";
    let mobileFull = "N/A";
    let desktopCompact = "N/A";
    let detached = "NO";
    if (cta) {
      const r = cta.getBoundingClientRect();
      const st = getComputedStyle(cta);
      const bg = st.backgroundColor;
      const looksButton =
        r.height >= 36 &&
        bg &&
        bg !== "rgba(0, 0, 0, 0)" &&
        bg !== "transparent" &&
        (parseFloat(st.paddingLeft) > 8 || parseFloat(st.paddingInlineStart || "0") > 8);
      ctaVisual = looksButton ? "PASS" : "FAIL";
      const wrap = cta.closest("[data-testid='group-payment-final-action']") || cta.parentElement;
      if (vw < 768 && wrap) {
        const wr = wrap.getBoundingClientRect();
        const pad =
          (parseFloat(getComputedStyle(wrap).paddingLeft) || 0) +
          (parseFloat(getComputedStyle(wrap).paddingRight) || 0);
        const contentW = Math.max(8, wr.width - pad);
        mobileFull = r.width >= contentW * 0.92 ? "YES" : "NO";
      } else if (vw >= 768) {
        desktopCompact = r.width < vw * 0.75 ? "YES" : "NO";
      }
      // Detached = large gap from payment details section
      const details = document.getElementById("group-payment-details-heading");
      if (details) {
        const dr = details.closest("section")?.getBoundingClientRect() || details.getBoundingClientRect();
        const gap = r.top - dr.bottom;
        if (gap > 120) detached = "YES";
      }
    }

    const summary = document.getElementById("group-booking-summary-heading");
    const method = document.getElementById("group-payment-method-heading");
    const finalAction = document.querySelector("[data-testid='group-payment-final-action']");
    let orderPass = "FAIL";
    if (summary && method && finalAction) {
      const sTop = summary.getBoundingClientRect().top;
      const mTop = method.getBoundingClientRect().top;
      const fTop = finalAction.getBoundingClientRect().top;
      // Mobile reading order: summary before method before final action
      if (vw < 1024) {
        orderPass = sTop <= mTop + 2 && mTop <= fTop + 2 ? "PASS" : "FAIL";
      } else {
        orderPass = mTop <= fTop + 2 ? "PASS" : "FAIL";
      }
    }

    // Header collision crude: h1 vs logo/account overlap
    const h1 = document.querySelector("h1");
    const header = document.querySelector("header");
    let headerCollision = 0;
    if (h1 && header) {
      const a = h1.getBoundingClientRect();
      const links = header.querySelectorAll("a, button");
      for (const el of links) {
        const b = el.getBoundingClientRect();
        const ix = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left));
        const iy = Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
        if (ix * iy >= 40) headerCollision += 1;
      }
    }

    return {
      PAYMENT_METHOD_CARDS: cardPass >= 3 ? "PASS" : "FAIL",
      PAYMENT_CARD_VISUAL_SEPARATION: separation >= 3 ? "PASS" : "FAIL",
      CTA_BUTTON_VISUAL: ctaVisual,
      MOBILE_CTA_FULL_WIDTH: mobileFull,
      DESKTOP_CTA_COMPACT: desktopCompact,
      CTA_NOT_DETACHED: detached === "NO" ? "YES" : "NO",
      BOOKING_SUMMARY_ORDER: orderPass,
      HEADER_COLLISION: headerCollision,
      methodCount: cards.length,
      LIVE_GROUP_PAYMENT_SOURCE_SIGNATURE: /Manual payment only/i.test(document.body.innerText)
        ? "CURRENT"
        : /Choose how you paid/i.test(document.body.innerText)
          ? "STALE"
          : "UNKNOWN",
    };
  });
}

export function verdictFromParts(parts) {
  const fails = parts.filter((p) => p && p.ok === false);
  if (!fails.length) return { SELF_REVIEW: "PASS", NOTES: parts.map((p) => p.note || "ok").join("; ") };
  return {
    SELF_REVIEW: `FAIL: ${fails.map((f) => f.reason || f.note || "fail").join(" | ")}`,
    NOTES: fails.map((f) => f.reason || f.note).join(" | "),
  };
}
