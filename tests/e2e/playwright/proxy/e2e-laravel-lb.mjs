/**
 * Plain round-robin load balancer for Next SSR → Laravel (no auth gate).
 *
 * Topology:
 *   Next SSR (LARAVEL_URL) → :8090 → Laravel workers :8001–:800N
 *   Browser → :9080 proxy → Next / Laravel (auth-gated shells)
 *
 * Env:
 *   E2E_LARAVEL_LB_PORT (8090)
 *   E2E_LARAVEL_ORIGINS (comma-separated)
 */
import http from "node:http";
import { URL } from "node:url";

const LB_PORT = Number(process.env.E2E_LARAVEL_LB_PORT ?? 8090);
const origins = (
  process.env.E2E_LARAVEL_ORIGINS ??
  process.env.E2E_LARAVEL_ORIGIN ??
  "http://127.0.0.1:8001"
)
  .split(",")
  .map((s) => s.trim().replace(/\/$/, ""))
  .filter(Boolean);

let rr = 0;
function nextOrigin() {
  const origin = origins[rr % origins.length];
  rr += 1;
  return origin;
}

const lbMetrics = {
  laravel_5xx_count: 0,
  /** @type {Array<Record<string, string|number>>} */
  laravel_5xx_events: [],
  seq: 0,
};

function record5xx(method, path, status, snippet = "") {
  lbMetrics.laravel_5xx_count += 1;
  lbMetrics.seq += 1;
  lbMetrics.laravel_5xx_events.push({
    order: lbMetrics.seq,
    timestamp: new Date().toISOString(),
    method,
    path: String(path).slice(0, 240),
    status,
    session_class: "ssr",
    source: "next-ssr-lb",
    body_snippet_safe: String(snippet)
      .slice(0, 180)
      .replace(/[^\x20-\x7E]/g, "?")
      .replace(/(password|token|cookie|authorization|secret)\s*[:=]\s*\S+/gi, "$1=[redacted]"),
  });
  if (lbMetrics.laravel_5xx_events.length > 50) lbMetrics.laravel_5xx_events.shift();
}

const server = http.createServer((clientReq, clientRes) => {
  const incoming = new URL(clientReq.url ?? "/", `http://127.0.0.1:${LB_PORT}`);
  if (incoming.pathname === "/__e2e/lb-metrics") {
    clientRes.writeHead(200, { "content-type": "application/json; charset=utf-8" });
    clientRes.end(JSON.stringify({ LARAVEL_5XX_COUNT: lbMetrics.laravel_5xx_count, LARAVEL_5XX_EVENTS: lbMetrics.laravel_5xx_events }, null, 2));
    return;
  }
  if (incoming.pathname === "/__e2e/lb-metrics/reset") {
    lbMetrics.laravel_5xx_count = 0;
    lbMetrics.laravel_5xx_events = [];
    lbMetrics.seq = 0;
    clientRes.writeHead(200, { "content-type": "application/json; charset=utf-8" });
    clientRes.end(JSON.stringify({ reset: true }));
    return;
  }

  const origin = nextOrigin();
  const upstream = new URL(incoming.pathname + incoming.search, origin);
  const headers = { ...clientReq.headers };
  delete headers["content-length"];
  delete headers.connection;
  headers.host = process.env.E2E_PUBLIC_HOST ?? "127.0.0.1:9080";
  headers["x-forwarded-host"] = headers.host;
  headers["x-forwarded-proto"] = "http";

  const upstreamReq = http.request(
    {
      protocol: upstream.protocol,
      hostname: upstream.hostname,
      port: upstream.port || 80,
      path: upstream.pathname + upstream.search,
      method: clientReq.method,
      headers,
    },
    (upstreamRes) => {
      const status = upstreamRes.statusCode ?? 502;
      if (status >= 500) {
        const chunks = [];
        upstreamRes.on("data", (c) => chunks.push(c));
        upstreamRes.on("end", () => {
          const body = Buffer.concat(chunks);
          record5xx(clientReq.method ?? "GET", upstream.pathname + upstream.search, status, body.toString("utf8").slice(0, 200));
          if (!clientRes.headersSent) {
            clientRes.writeHead(status, upstreamRes.headers);
          }
          clientRes.end(body);
        });
        return;
      }
      clientRes.writeHead(status, upstreamRes.headers);
      upstreamRes.pipe(clientRes);
    },
  );
  upstreamReq.on("error", (error) => {
    record5xx(clientReq.method ?? "GET", upstream.pathname, 502, error.message);
    if (!clientRes.headersSent) {
      clientRes.writeHead(502, { "content-type": "text/plain; charset=utf-8" });
    }
    clientRes.end(`E2E Laravel LB error: ${error.message}`);
  });
  upstreamReq.setTimeout(20_000, () => {
    upstreamReq.destroy(new Error("laravel lb upstream timeout"));
  });
  clientReq.pipe(upstreamReq);
});

server.listen(LB_PORT, "127.0.0.1", () => {
  console.log("E2E_LARAVEL_LB=READY");
  console.log(`E2E_LARAVEL_LB_ORIGIN=http://127.0.0.1:${LB_PORT}`);
  console.log(`E2E_LARAVEL_ORIGINS=${origins.join(",")}`);
  console.log(`LARAVEL_E2E_WORKERS=${origins.length}`);
});
