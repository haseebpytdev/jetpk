/* ==========================================================================
   JetPakistan — Flight Cards controller  (vanilla JS, no dependencies)
   --------------------------------------------------------------------------
   Responsibilities
     1. Toggle the inline branded-fare tray on each result card.
     2. Open a SINGLE shared modal for "Flight details" and "Fare summary",
        building its contents from a JSON payload on the card (data-flight).
        -> one modal for the whole page, so 100+ results stay cheap.
     3. Tab switching inside the Fare Summary modal.
     4. Accessibility: ESC to close, backdrop close, focus return, basic trap.

   Wire-up in markup
     - Card root:            <article class="jp-flight" data-flight='{...}'>
     - Toggle fare tray:     any element with  data-fare-toggle
     - Open flight details:  data-modal="flight-details"
     - Open a fare summary:  data-modal="fare-summary" data-fare="<index>"
     - The shared modal:     one element  #jpModal  (see markup in INTEGRATION.md)

   Usage:  JPFlights.init();           // call once after DOM ready
   ========================================================================== */
(function (global) {
  "use strict";

  /* -- tiny SVG icon set (kept here so markup stays clean) ---------------- */
  const I = {
    cabin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="7" width="12" height="14" rx="2"/><path d="M9 7V4h6v3M10 11v6M14 11v6"/></svg>',
    checked: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M9 8V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v3M12 12v4"/></svg>',
    meal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 3v7a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2V3M6 12v9M18 3c-1.7 0-3 2.2-3 5s1.3 4 3 4v9"/></svg>',
    refund: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 1 3 6.7M3 21v-5h5"/></svg>',
    plane: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 15.5 14 12V5.5A1.5 1.5 0 0 0 12.5 4 1.5 1.5 0 0 0 11 5.5V12l-7 3.5V17l7-2v3l-2 1.3V21l3.5-1 3.5 1v-1.7L13 18v-3l8 2z"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    caret: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>'
  };
  const iconFor = (k) => I[k] || "";

  const esc = (s) => String(s == null ? "" : s)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");

  const flightOf = (card) => {
    try { return JSON.parse(card.getAttribute("data-flight") || "{}"); }
    catch (e) { console.warn("[JPFlights] bad data-flight JSON on", card, e); return {}; }
  };

  /* ================================================= FARE TRAY TOGGLE ==== */
  function toggleTray(card) {
    const open = card.classList.toggle("is-expanded");
    card.querySelectorAll("[data-fare-toggle]").forEach((btn) => {
      if (btn.hasAttribute("aria-expanded")) btn.setAttribute("aria-expanded", String(open));
    });
    return open;
  }

  /* ========================================================= MODAL ====== */
  let modal, mBody, mFoot, mTitle, mSub, lastFocus = null;

  function ensureModal() {
    modal = document.getElementById("jpModal");
    if (!modal) { console.error("[JPFlights] #jpModal not found in DOM."); return false; }
    mBody  = modal.querySelector("[data-modal-body]");
    mFoot  = modal.querySelector("[data-modal-foot]");
    mTitle = modal.querySelector("[data-modal-title]");
    mSub   = modal.querySelector("[data-modal-sub]");
    return true;
  }

  function openModal({ title, sub, body, foot }) {
    if (!modal && !ensureModal()) return;
    lastFocus = document.activeElement;
    mTitle.textContent = title || "";
    mSub.textContent   = sub || "";
    mBody.innerHTML    = body || "";
    mFoot.innerHTML    = foot || "";
    mFoot.style.display = foot ? "" : "none";
    modal.hidden = false;
    // next frame -> allow CSS transition
    requestAnimationFrame(() => {
      modal.classList.add("is-open");
      const focusable = modal.querySelector(
        '[data-modal-close], button:not([disabled]), [href], input, [tabindex]:not([tabindex="-1"])'
      );
      (focusable || modal).focus?.();
    });
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove("is-open");
    const done = () => { modal.hidden = true; modal.removeEventListener("transitionend", done); };
    const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce) done(); else {
      modal.addEventListener("transitionend", done);
      setTimeout(done, 320); // safety net
    }
    document.body.style.overflow = "";
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  /* -- content builders ---------------------------------------------------- */
  function buildFlightDetails(f) {
    const segs = Array.isArray(f.segments) && f.segments.length
      ? f.segments
      : [{
          airlineName: f.airline && f.airline.name, airlineCode: f.airline && f.airline.code,
          flightNo: f.flightNumber, cabin: f.cabin,
          dep: f.departure, arr: f.arrival, duration: f.duration, stops: f.stops
        }];

    const legHTML = (s) => `
      <div class="jp-leg">
        <div class="jp-leg__col">
          <span class="jp-leg__time">${esc(s.dep && s.dep.time)}</span>
          <span class="jp-leg__code">${esc(s.dep && s.dep.code)}</span>
          <span class="jp-leg__place">${esc(s.dep && s.dep.city)}</span>
          <span class="jp-leg__date">${esc(s.dep && s.dep.date)}</span>
        </div>
        <div class="jp-leg__mid">
          <span class="jp-leg__dur">${esc(s.duration || "")}</span>
          <span class="jp-leg__track"></span>
        </div>
        <div class="jp-leg__col jp-leg__col--arr">
          <span class="jp-leg__time">${esc(s.arr && s.arr.time)}</span>
          <span class="jp-leg__code">${esc(s.arr && s.arr.code)}</span>
          <span class="jp-leg__place">${esc(s.arr && s.arr.city)}</span>
          <span class="jp-leg__date">${esc(s.arr && s.arr.date)}</span>
        </div>
      </div>`;

    const segHTML = segs.map((s, i) => {
      const layover = i > 0 && segs[i - 1].arr && s.dep
        ? `<div class="jp-layover">Layover in ${esc(s.dep.city || s.dep.code)}${s.layover ? " · " + esc(s.layover) : ""}</div>`
        : "";
      const stopsLabel = (s.stops || (i === 0 ? f.stops : "")) || "Direct";
      return `${layover}
        <div class="jp-seg">
          <div class="jp-seg__head">
            ${iconForPlaneTile()}
            <span class="jp-seg__dir">${i === 0 ? "Outbound" : "Continuing"} · ${esc(s.dep && s.dep.code)} → ${esc(s.arr && s.arr.code)}</span>
          </div>
          <div class="jp-seg__meta">
            <span class="jp-pill">${esc(s.duration || f.duration || "")}</span>
            <span class="jp-pill jp-pill--accent">${esc(stopsLabel)}</span>
            ${s.cabin ? `<span class="jp-pill">Cabin ${esc(s.cabin)}</span>` : ""}
          </div>
          ${legHTML(s)}
          <div class="jp-seg__foot">
            <span class="jp-flight-no">
              ${esc(s.airlineName || (f.airline && f.airline.name) || "")}
              <b>${esc((s.airlineCode || (f.airline && f.airline.code) || "") + " " + (s.flightNo ? String(s.flightNo).replace(/^\D+/, "") : (f.flightNumber || "").replace(/^\D+\s?/, "")))}</b>
            </span>
          </div>
        </div>`;
    }).join("");

    return { title: "Flight Details", sub: "Review connections, segments, and layovers.", body: segHTML, foot: "" };
  }

  function iconForPlaneTile() {
    return `<span style="width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:var(--jp-orange-050);color:var(--jp-orange)">
      <span style="width:15px;height:15px;display:block">${iconFor("plane")}</span></span>`;
  }

  function buildFareSummary(f, idx) {
    const fare = (f.fares && f.fares[idx]) || {};
    const cur  = fare.currency || "PKR";
    const b    = fare.breakdown || {};
    const dep  = f.departure || {}, arr = f.arrival || {};
    const routeLine = `<span class="jp-route-mini">${iconFor("plane")} ${esc(dep.code)} → ${esc(arr.code)}</span>`;

    const baggage = `
      ${routeLine}
      <div class="jp-kv">
        <div class="jp-kv__row">
          <span class="jp-kv__k">${iconFor("cabin")} Carry-on baggage</span>
          <span class="jp-kv__v">${esc(fare.carryOn || "Airline policy")}</span>
        </div>
        <div class="jp-kv__row">
          <span class="jp-kv__k">${iconFor("checked")} Checked baggage</span>
          <span class="jp-kv__v ${/(not|—|0)/i.test(fare.checkIn || "") ? "jp-kv__v--off" : "jp-kv__v--ok"}">${esc(fare.checkIn || "Not included")}</span>
        </div>
        <div class="jp-kv__row">
          <span class="jp-kv__k">${iconFor("meal")} Meal</span>
          <span class="jp-kv__v ${/(not|—)/i.test(fare.meal || "") ? "jp-kv__v--off" : ""}">${esc(fare.meal || "Not specified")}</span>
        </div>
      </div>`;

    const policy = `
      <div class="jp-kv">
        <div class="jp-kv__row">
          <span class="jp-kv__k">${iconFor("refund")} Refund</span>
          <span class="jp-kv__v ${/refundable/i.test(fare.refund || "") ? "jp-kv__v--ok" : "jp-kv__v--off"}">${esc(fare.refund || "Non-refundable")}</span>
        </div>
        <div class="jp-kv__row">
          <span class="jp-kv__k">Changes</span>
          <span class="jp-kv__v">${esc(fare.changes || "As per airline rules")}</span>
        </div>
        <div class="jp-kv__row">
          <span class="jp-kv__k">Supplier</span>
          <span class="jp-kv__v">${esc(f.source || "—")}</span>
        </div>
      </div>`;

    const details = `
      <table class="jp-table">
        <thead><tr><th>Passenger</th><th>Qty</th><th>Base fare</th><th>Taxes &amp; fees</th><th>Total</th></tr></thead>
        <tbody>
          <tr>
            <td>Adult</td><td>${esc(fare.pax || 1)}</td>
            <td>${cur} ${esc(b.base || "—")}</td>
            <td>${cur} ${esc(b.taxes || "—")}</td>
            <td>${cur} ${esc(b.total || fare.price || "—")}</td>
          </tr>
        </tbody>
      </table>`;

    const tablist = ["Baggage Policy", "Fare Policy", "Fare Details"];
    const panels  = [baggage, policy, details];
    const tabsHTML = `
      <div class="jp-tabs" role="tablist" aria-label="Fare summary">
        ${tablist.map((t, i) => `<button class="jp-tab" role="tab" id="jpTab${i}"
            aria-controls="jpPanel${i}" aria-selected="${i === 0}" data-tab="${i}">${t}</button>`).join("")}
      </div>
      ${panels.map((p, i) => `<div class="jp-tabpanel" role="tabpanel" id="jpPanel${i}"
          aria-labelledby="jpTab${i}" ${i === 0 ? "" : "hidden"}>${p}</div>`).join("")}`;

    const foot = `
      <div class="jp-total">
        <span class="jp-total__label">Grand total · incl. taxes &amp; fees</span>
        <span class="jp-total__amount">${cur} ${esc(b.total || fare.price || "—")}</span>
        <span class="jp-total__note">Fares may change until booking is confirmed.</span>
      </div>
      <div class="jp-foot__actions">
        <button class="jp-btn jp-btn--ghost" data-modal-close>Close</button>
        <button class="jp-btn jp-btn--primary" data-select-fare="${idx}">Select fare</button>
      </div>`;

    return { title: "Fare Summary", sub: `${esc(fare.name || "Fare")} — review baggage, policy, and pricing.`, body: tabsHTML, foot };
  }

  /* ==================================================== EVENT BINDING ==== */
  function onClick(e) {
    // fare tray
    const trayBtn = e.target.closest("[data-fare-toggle]");
    if (trayBtn) {
      const card = trayBtn.closest(".jp-flight");
      if (card) { e.preventDefault(); toggleTray(card); }
      return;
    }

    // open modal
    const opener = e.target.closest("[data-modal]");
    if (opener) {
      e.preventDefault();
      const card = opener.closest(".jp-flight");
      const f = card ? flightOf(card) : {};
      const kind = opener.getAttribute("data-modal");
      if (kind === "fare-summary") {
        const idx = parseInt(opener.getAttribute("data-fare") || "0", 10) || 0;
        openModal(buildFareSummary(f, idx));
      } else {
        openModal(buildFlightDetails(f));
      }
      return;
    }

    // close modal
    if (e.target.closest("[data-modal-close]")) { e.preventDefault(); closeModal(); return; }

    // select fare from within modal -> emit event for host app
    const sel = e.target.closest("[data-select-fare]");
    if (sel) {
      const idx = parseInt(sel.getAttribute("data-select-fare") || "0", 10) || 0;
      document.dispatchEvent(new CustomEvent("jp:selectFare", { detail: { fareIndex: idx } }));
      closeModal();
      return;
    }

    // tabs inside fare summary
    const tab = e.target.closest(".jp-tab");
    if (tab && modal && modal.contains(tab)) {
      const i = tab.getAttribute("data-tab");
      modal.querySelectorAll(".jp-tab").forEach((t) => t.setAttribute("aria-selected", String(t === tab)));
      modal.querySelectorAll(".jp-tabpanel").forEach((p) => { p.hidden = p.id !== "jpPanel" + i; });
    }
  }

  function onKeydown(e) {
    if (!modal || modal.hidden) return;
    if (e.key === "Escape") { closeModal(); return; }
    if (e.key === "Tab") {                                   // simple focus trap
      const f = modal.querySelectorAll(
        'button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }

  /* ================================================== ICON HYDRATION ===== */
  // Any element with data-icon="cabin|checked|meal|refund|plane|caret|close"
  // gets its SVG injected. Lets your Blade markup stay icon-free.
  function hydrateIcons(root) {
    (root || document).querySelectorAll("[data-icon]").forEach((el) => {
      if (el.dataset.iconDone) return;
      el.innerHTML = iconFor(el.getAttribute("data-icon"));
      el.dataset.iconDone = "1";
    });
  }

  /* ============================================================ INIT ===== */
  const JPFlights = {
    init(opts) {
      ensureModal();
      hydrateIcons(document);
      document.addEventListener("click", onClick);
      document.addEventListener("keydown", onKeydown);
      return this;
    },
    open: openModal,
    close: closeModal,
    icons: I,
    hydrateIcons
  };

  global.JPFlights = JPFlights;
  if (document.readyState !== "loading") { /* caller may init when ready */ }
})(window);
