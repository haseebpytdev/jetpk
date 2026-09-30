/**
 * Plain round-robin load balancer for Next SSR → Laravel (no auth gate).
 *
 * Topology:
 *   Next SSR (LARAVEL_URL) → :8090 → Laravel workers :8001–:800N
 *   Browser → :9080 proxy → Next / Laravel (auth-gated shells)
 *
 * Avoids nesting Next SSR through the auth proxy under crawl load.
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

const server = http.createServer((clientReq, clientRes) => {
  const origin = nextOrigin();
  const incoming = new URL(clientReq.url ?? "/", `http://127.0.0.1:${LB_PORT}`);
  const upstream = new URL(incoming.pathname + incoming.search, origin);
  const headers = { ...clientReq.headers };
  delete headers["content-length"];
  delete headers.connection;
  // Preserve Host as the public E2E origin so session cookies validate.
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
      clientRes.writeHead(upstreamRes.statusCode ?? 502, upstreamRes.headers);
      upstreamRes.pipe(clientRes);
    },
  );
  upstreamReq.on("error", (error) => {
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
