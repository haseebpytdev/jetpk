/** Inline HTML boot: hydrate from Book Now sessionStorage prime, else start passengers GET at document parse. */
export const PASSENGERS_EARLY_FETCH_INLINE = `(function(){
  try {
    if (typeof window === "undefined") return;
    var w = window;
    w.__jpTravelerBoot = w.__jpTravelerBoot || { marks: {}, meta: {} };
    function mark(k) {
      if (w.__jpTravelerBoot.marks[k] == null) w.__jpTravelerBoot.marks[k] = performance.now();
    }
    mark("TRAVELER_DOCUMENT_READY");
    mark("CLIENT_BOOT_START");
    if (!/\\/booking\\/passengers/.test(location.pathname)) return;
    var params = new URLSearchParams(location.search || "");
    var searchId = (params.get("search_id") || "").trim();
    var offerId = (params.get("offer_id") || params.get("flight_id") || "").trim();
    if (!searchId || !offerId) {
      w.__jpTravelerBoot.meta.early_fetch_skipped = "missing_handoff";
      mark("CLIENT_BOOT_END");
      return;
    }
    var canonical = new URLSearchParams();
    params.forEach(function (value, key) {
      if (value !== undefined && value !== "") canonical.set(key, value);
    });
    canonical.set("format", "json");
    var key = canonical.toString();
    if (w.__jpPassengersPrime && w.__jpPassengersPrime.key === key && w.__jpPassengersPrime.promise) {
      mark("PASSENGER_REQUEST_SCHEDULED");
      mark("CLIENT_BOOT_END");
      return;
    }
    mark("PASSENGER_REQUEST_SCHEDULED");
    try {
      var raw = sessionStorage.getItem("jp-passengers-context-prime");
      if (raw) {
        var record = JSON.parse(raw);
        if (record && record.key === key && typeof record.at === "number" && (Date.now() - record.at) <= 120000 && record.data && record.data.ok === true) {
          mark("SESSION_PRIME_HIT");
          w.__jpPassengersPrime = {
            key: key,
            promise: Promise.resolve({ ok: true, data: record.data, source: "session_prime" }),
            source: "session_prime"
          };
          w.__jpTravelerBoot.meta.early_fetch_source = "session_prime";
          w.__jpTravelerBoot.meta.early_fetch_key = key;
          try { sessionStorage.removeItem("jp-passengers-context-prime"); } catch (e) {}
          mark("CLIENT_BOOT_END");
          return;
        }
      }
    } catch (e) {}
    mark("PASSENGER_FETCH_CALLED");
    mark("PASSENGER_FETCH_REQUEST_START");
    mark("EARLY_FETCH_START");
    var headers = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    var promise = fetch("/laravel/booking/passengers?" + key, {
      credentials: "include",
      headers: headers
    }).then(function (response) {
      var contentType = response.headers.get("content-type") || "";
      return (contentType.indexOf("application/json") !== -1 ? response.json() : Promise.resolve(null)).then(function (payload) {
        if (!response.ok) {
          return {
            ok: false,
            status: response.status,
            message: (payload && payload.message) || "Request failed.",
            errors: payload && payload.errors,
            data: payload,
            _response: response
          };
        }
        return { ok: true, data: payload, _response: response };
      });
    }).catch(function () {
      return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
    });
    w.__jpPassengersPrime = { key: key, promise: promise, source: "inline_document" };
    w.__jpTravelerBoot.meta.early_fetch_source = "inline_document";
    w.__jpTravelerBoot.meta.early_fetch_key = key;
    mark("CLIENT_BOOT_END");
  } catch (e) {}
})();`;
