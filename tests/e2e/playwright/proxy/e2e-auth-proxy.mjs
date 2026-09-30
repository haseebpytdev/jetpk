/**
 * Same-origin reverse proxy for PR #57 authenticated E2E.
 *
 * Topology:
 *   Browser → :9080 → Next :3001 (dashboard shells, gated)
 *                    → Laravel worker pool (round-robin)
 *
 * Auth gate: GET /api/dashboard/session?portal=… (no long-lived auth cache).
 * Concurrent identical checks share one in-flight Laravel validation (single-flight).
 *
 * Env:
 *   E2E_PROXY_PORT (9080)
 *   E2E_LARAVEL_ORIGIN or E2E_LARAVEL_ORIGINS (comma-separated pool)
 *   E2E_DASHBOARD_ORIGIN (http://127.0.0.1:3001)
 *   E2E_METRICS_PATH (/__e2e/metrics) — sanitized counters only
 */
import http from "node:http";
import { URL } from "node:url";
import crypto from "node:crypto";

const PROXY_PORT = Number(process.env.E2E_PROXY_PORT ?? 9080);
const DASHBOARD_ORIGIN = (process.env.E2E_DASHBOARD_ORIGIN ?? "http://127.0.0.1:3001").replace(
  /\/$/,
  "",
);
const PROXY_HOST = `127.0.0.1:${PROXY_PORT}`;
const METRICS_PATH = process.env.E2E_METRICS_PATH ?? "/__e2e/metrics";

const laravelOrigins = (
  process.env.E2E_LARAVEL_ORIGINS ??
  process.env.E2E_LARAVEL_ORIGIN ??
  "http://127.0.0.1:8000"
)
  .split(",")
  .map((s) => s.trim().replace(/\/$/, ""))
  .filter(Boolean);

let rr = 0;
function nextLaravelOrigin() {
  const origin = laravelOrigins[rr % laravelOrigins.length];
  rr += 1;
  return origin;
}

const metrics = {
  laravel_worker_count: laravelOrigins.length,
  crawl_request_count: 0,
  peak_inflight_laravel_requests: 0,
  inflight_laravel_requests: 0,
  auth_gate_request_count: 0,
  auth_gate_coalesced_count: 0,
  auth_gate_502_count: 0,
  laravel_5xx_count: 0,
  next_5xx_count: 0,
  sqlite_lock_errors: 0,
};

/** @type {Map<string, Promise<{ status: number, body: string, portalType: string }>>} */
const authSingleFlight = new Map();

function resolveUpstream(pathname) {
  if (pathname === METRICS_PATH) {
    return { kind: "metrics" };
  }
  if (pathname.startsWith("/_next/") || pathname.startsWith("/dashboard-next/")) {
    return { origin: DASHBOARD_ORIGIN, path: pathname, kind: "next" };
  }
  if (pathname === "/laravel" || pathname.startsWith("/laravel/")) {
    const stripped = pathname === "/laravel" ? "/" : pathname.slice("/laravel".length) || "/";
    return { origin: nextLaravelOrigin(), path: stripped, kind: "laravel" };
  }
  const shell = pathname.match(/^\/(admin|staff)\/dashboard(\/|$)/);
  if (shell) {
    return {
      origin: DASHBOARD_ORIGIN,
      path: pathname,
      kind: "next-shell",
      portal: shell[1],
    };
  }
  return { origin: nextLaravelOrigin(), path: pathname, kind: "laravel" };
}

function trackLaravelStart() {
  metrics.inflight_laravel_requests += 1;
  metrics.peak_inflight_laravel_requests = Math.max(
    metrics.peak_inflight_laravel_requests,
    metrics.inflight_laravel_requests,
  );
}

function trackLaravelEnd(status, bodySnippet = "") {
  metrics.inflight_laravel_requests = Math.max(0, metrics.inflight_laravel_requests - 1);
  if (status >= 500) {
    metrics.laravel_5xx_count += 1;
  }
  if (/database is locked|SQLITE_BUSY/i.test(bodySnippet)) {
    metrics.sqlite_lock_errors += 1;
  }
}

function sessionFingerprint(cookieHeader) {
  const raw = cookieHeader ?? "";
  // Hash only — never log cookie values.
  return crypto.createHash("sha256").update(raw).digest("hex").slice(0, 24);
}

function laravelGet(pathname, cookieHeader) {
  const origin = nextLaravelOrigin();
  return new Promise((resolve, reject) => {
    const upstream = new URL(pathname, origin);
    trackLaravelStart();
    const req = http.request(
      {
        protocol: upstream.protocol,
        hostname: upstream.hostname,
        port: upstream.port || 80,
        path: upstream.pathname + upstream.search,
        method: "GET",
        headers: {
          host: PROXY_HOST,
          accept: "application/json",
          cookie: cookieHeader ?? "",
          "x-forwarded-host": PROXY_HOST,
          "x-forwarded-proto": "http",
        },
      },
      (res) => {
        const chunks = [];
        res.on("data", (c) => chunks.push(c));
        res.on("end", () => {
          const body = Buffer.concat(chunks).toString("utf8");
          const status = res.statusCode ?? 502;
          trackLaravelEnd(status, body.slice(0, 200));
          resolve({ status, body });
        });
      },
    );
    req.on("error", (error) => {
      trackLaravelEnd(502, error.message);
      reject(error);
    });
    req.setTimeout(15_000, () => {
      req.destroy(new Error("laravel request timeout"));
    });
    req.end();
  });
}

async function authorizePortal(portal, cookieHeader) {
  metrics.auth_gate_request_count += 1;
  const key = `${portal}:${sessionFingerprint(cookieHeader)}`;
  const existing = authSingleFlight.get(key);
  if (existing) {
    metrics.auth_gate_coalesced_count += 1;
    return existing;
  }

  const promise = (async () => {
    let lastError = null;
    for (let attempt = 0; attempt < 3; attempt += 1) {
      try {
        const session = await laravelGet(`/api/dashboard/session?portal=${portal}`, cookieHeader);
        let portalType = "";
        try {
          const json = JSON.parse(session.body);
          portalType = String(json?.data?.portalType ?? json?.portalType ?? "");
        } catch {
          portalType = "";
        }
        return { status: session.status, body: session.body, portalType };
      } catch (error) {
        lastError = error;
        await new Promise((r) => setTimeout(r, 100 * (attempt + 1)));
      }
    }
    metrics.auth_gate_502_count += 1;
    throw lastError ?? new Error("auth gate unavailable");
  })();

  authSingleFlight.set(key, promise);
  try {
    return await promise;
  } finally {
    authSingleFlight.delete(key);
  }
}

function forward(clientReq, clientRes, origin, path, kind) {
  metrics.crawl_request_count += 1;
  const incoming = new URL(clientReq.url ?? "/", `http://${PROXY_HOST}`);
  const upstream = new URL(path + incoming.search, origin);
  const headers = { ...clientReq.headers };
  delete headers["content-length"];
  delete headers.connection;
  delete headers["transfer-encoding"];
  delete headers["keep-alive"];
  delete headers["proxy-connection"];

  const isLaravel = kind === "laravel";
  if (isLaravel) {
    headers.host = PROXY_HOST;
    headers["x-forwarded-host"] = PROXY_HOST;
    headers["x-forwarded-proto"] = "http";
    headers["x-forwarded-for"] = clientReq.socket.remoteAddress ?? "127.0.0.1";
    headers["x-forwarded-port"] = String(PROXY_PORT);
    trackLaravelStart();
  } else {
    headers.host = upstream.host;
  }

  const upstreamReq = http.request(
    {
      protocol: upstream.protocol,
      hostname: upstream.hostname,
      port: upstream.port || (upstream.protocol === "https:" ? 443 : 80),
      path: upstream.pathname + upstream.search,
      method: clientReq.method,
      headers,
    },
    (upstreamRes) => {
      const status = upstreamRes.statusCode ?? 502;
      if (isLaravel) {
        trackLaravelEnd(status);
      } else if (status >= 500) {
        metrics.next_5xx_count += 1;
      }
      clientRes.writeHead(status, upstreamRes.headers);
      upstreamRes.pipe(clientRes);
    },
  );

  upstreamReq.on("error", (error) => {
    if (isLaravel) {
      trackLaravelEnd(502, error.message);
      metrics.auth_gate_502_count += kind === "next-shell" ? 0 : 0;
    }
    if (!clientRes.headersSent) {
      clientRes.writeHead(502, { "content-type": "text/plain; charset=utf-8" });
    }
    clientRes.end(`E2E proxy upstream error: ${error.message}`);
  });

  clientReq.pipe(upstreamReq);
}

async function gateNextShell(clientReq, clientRes, portal, pathname) {
  try {
    const cookie = clientReq.headers.cookie ?? "";
    const session = await authorizePortal(portal, cookie);
    if (session.status === 401 || session.status === 403) {
      clientRes.writeHead(session.status, { "content-type": "application/json" });
      clientRes.end(session.body || '{"message":"Forbidden"}');
      return;
    }
    if (session.status >= 400) {
      clientRes.writeHead(403, { "content-type": "text/plain; charset=utf-8" });
      clientRes.end("E2E auth gate: dashboard session denied");
      return;
    }
    if (session.portalType && session.portalType !== portal) {
      clientRes.writeHead(403, { "content-type": "text/plain; charset=utf-8" });
      clientRes.end(`E2E auth gate: portal mismatch (have=${session.portalType}, need=${portal})`);
      return;
    }
    forward(clientReq, clientRes, DASHBOARD_ORIGIN, pathname, "next");
  } catch (error) {
    metrics.auth_gate_502_count += 1;
    clientRes.writeHead(502, { "content-type": "text/plain; charset=utf-8" });
    clientRes.end(`E2E auth gate error: ${error.message}`);
  }
}

function writeMetrics(clientRes) {
  clientRes.writeHead(200, { "content-type": "application/json; charset=utf-8" });
  clientRes.end(
    JSON.stringify(
      {
        LARAVEL_WORKER_COUNT: metrics.laravel_worker_count,
        CRAWL_REQUEST_COUNT: metrics.crawl_request_count,
        PEAK_INFLIGHT_LARAVEL_REQUESTS: metrics.peak_inflight_laravel_requests,
        AUTH_GATE_REQUEST_COUNT: metrics.auth_gate_request_count,
        AUTH_GATE_COALESCED_COUNT: metrics.auth_gate_coalesced_count,
        AUTH_GATE_502_COUNT: metrics.auth_gate_502_count,
        LARAVEL_5XX_COUNT: metrics.laravel_5xx_count,
        NEXT_5XX_COUNT: metrics.next_5xx_count,
        SQLITE_LOCK_ERRORS: metrics.sqlite_lock_errors,
      },
      null,
      2,
    ),
  );
}

function proxyRequest(clientReq, clientRes) {
  const incoming = new URL(clientReq.url ?? "/", `http://${PROXY_HOST}`);
  const resolved = resolveUpstream(incoming.pathname);

  if (resolved.kind === "metrics") {
    writeMetrics(clientRes);
    return;
  }
  if (resolved.kind === "next-shell") {
    void gateNextShell(clientReq, clientRes, resolved.portal, resolved.path);
    return;
  }
  forward(clientReq, clientRes, resolved.origin, resolved.path, resolved.kind);
}

const server = http.createServer(proxyRequest);
server.listen(PROXY_PORT, "127.0.0.1", () => {
  console.log("E2E_AUTH_PROXY=READY");
  console.log(`E2E_PROXY_ORIGIN=http://127.0.0.1:${PROXY_PORT}`);
  console.log(`E2E_LARAVEL_ORIGINS=${laravelOrigins.join(",")}`);
  console.log(`LARAVEL_E2E_WORKERS=${laravelOrigins.length}`);
  console.log(`E2E_DASHBOARD_ORIGIN=${DASHBOARD_ORIGIN}`);
  console.log("E2E_AUTH_GATE_MODE=single-flight");
  console.log(`E2E_METRICS=${METRICS_PATH}`);
});
