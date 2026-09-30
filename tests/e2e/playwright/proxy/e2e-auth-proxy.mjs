/**
 * Same-origin reverse proxy for PR #57 authenticated E2E.
 *
 * Routing:
 * - /_next/*, /dashboard-next/* → Next assets
 * - /laravel/* → Laravel (prefix stripped)
 * - /admin|staff/dashboard* HTML shells → Next, gated by Laravel session API
 * - everything else → Laravel
 *
 * Auth gate (mirrors production intent without relying on Laravel→Next HTTP proxy):
 *   GET /api/dashboard/session?portal=<portal> with browser cookies.
 *   Wrong/missing portal → 403/401 returned to browser (no Next shell).
 */
import http from "node:http";
import { URL } from "node:url";

const PROXY_PORT = Number(process.env.E2E_PROXY_PORT ?? 9080);
const LARAVEL_ORIGIN = (process.env.E2E_LARAVEL_ORIGIN ?? "http://127.0.0.1:8000").replace(/\/$/, "");
const DASHBOARD_ORIGIN = (process.env.E2E_DASHBOARD_ORIGIN ?? "http://127.0.0.1:3001").replace(
  /\/$/,
  "",
);
const PROXY_HOST = `127.0.0.1:${PROXY_PORT}`;

/** @type {Map<string, { status: number, body: string, portalType: string, expires: number }>} */
const sessionGateCache = new Map();
const SESSION_GATE_TTL_MS = 45_000;

function resolveUpstream(pathname) {
  if (pathname.startsWith("/_next/") || pathname.startsWith("/dashboard-next/")) {
    return { origin: DASHBOARD_ORIGIN, path: pathname, kind: "next" };
  }

  if (pathname === "/laravel" || pathname.startsWith("/laravel/")) {
    const stripped = pathname === "/laravel" ? "/" : pathname.slice("/laravel".length) || "/";
    return { origin: LARAVEL_ORIGIN, path: stripped, kind: "laravel" };
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

  return { origin: LARAVEL_ORIGIN, path: pathname, kind: "laravel" };
}

function forward(clientReq, clientRes, origin, path, kind) {
  const upstream = new URL(path + new URL(clientReq.url ?? "/", `http://${PROXY_HOST}`).search, origin);
  const headers = { ...clientReq.headers };
  delete headers["content-length"];
  delete headers.connection;
  delete headers["transfer-encoding"];
  delete headers["keep-alive"];
  delete headers["proxy-connection"];

  if (kind === "laravel" || kind === "auth-check") {
    headers.host = PROXY_HOST;
    headers["x-forwarded-host"] = PROXY_HOST;
    headers["x-forwarded-proto"] = "http";
    headers["x-forwarded-for"] = clientReq.socket.remoteAddress ?? "127.0.0.1";
    headers["x-forwarded-port"] = String(PROXY_PORT);
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
      clientRes.writeHead(upstreamRes.statusCode ?? 502, upstreamRes.headers);
      upstreamRes.pipe(clientRes);
    },
  );

  upstreamReq.on("error", (error) => {
    if (!clientRes.headersSent) {
      clientRes.writeHead(502, { "content-type": "text/plain; charset=utf-8" });
    }
    clientRes.end(`E2E proxy upstream error: ${error.message}`);
  });

  if (kind === "auth-check") {
    upstreamReq.end();
  } else {
    clientReq.pipe(upstreamReq);
  }
}

function laravelGet(pathname, cookieHeader) {
  return new Promise((resolve, reject) => {
    const upstream = new URL(pathname, LARAVEL_ORIGIN);
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
          resolve({
            status: res.statusCode ?? 502,
            body: Buffer.concat(chunks).toString("utf8"),
          });
        });
      },
    );
    req.on("error", reject);
    req.setTimeout(10_000, () => {
      req.destroy(new Error("auth check timeout"));
    });
    req.end();
  });
}

async function gateNextShell(clientReq, clientRes, portal, pathname) {
  try {
    const cookie = clientReq.headers.cookie ?? "";
    const cacheKey = `${portal}::${cookie}`;
    const now = Date.now();
    let portalType = "";
    let cached = sessionGateCache.get(cacheKey);
    if (cached && cached.expires > now) {
      portalType = cached.portalType;
      if (cached.status === 401 || cached.status === 403 || cached.status >= 400) {
        clientRes.writeHead(cached.status >= 400 && cached.status < 500 ? cached.status : 403, {
          "content-type": "application/json",
        });
        clientRes.end(cached.body || '{"message":"Forbidden"}');
        return;
      }
    } else {
      let session = null;
      let lastError = null;
      for (let attempt = 0; attempt < 3; attempt += 1) {
        try {
          session = await laravelGet(`/api/dashboard/session?portal=${portal}`, cookie);
          if (session.status < 500) {
            break;
          }
        } catch (error) {
          lastError = error;
          session = null;
        }
        await new Promise((r) => setTimeout(r, 250 * (attempt + 1)));
      }
      if (!session) {
        throw lastError ?? new Error("auth gate unavailable");
      }
      try {
        const json = JSON.parse(session.body);
        portalType = String(json?.data?.portalType ?? json?.portalType ?? "");
      } catch {
        portalType = "";
      }
      sessionGateCache.set(cacheKey, {
        status: session.status,
        body: session.body,
        portalType,
        expires: now + SESSION_GATE_TTL_MS,
      });
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
    }

    if (portalType && portalType !== portal) {
      clientRes.writeHead(403, { "content-type": "text/plain; charset=utf-8" });
      clientRes.end(`E2E auth gate: portal mismatch (have=${portalType}, need=${portal})`);
      return;
    }

    forward(clientReq, clientRes, DASHBOARD_ORIGIN, pathname, "next");
  } catch (error) {
    clientRes.writeHead(502, { "content-type": "text/plain; charset=utf-8" });
    clientRes.end(`E2E auth gate error: ${error.message}`);
  }
}

function proxyRequest(clientReq, clientRes) {
  const incoming = new URL(clientReq.url ?? "/", `http://${PROXY_HOST}`);
  const resolved = resolveUpstream(incoming.pathname);

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
  console.log(`E2E_LARAVEL_ORIGIN=${LARAVEL_ORIGIN}`);
  console.log(`E2E_DASHBOARD_ORIGIN=${DASHBOARD_ORIGIN}`);
  console.log("E2E_DASHBOARD_SHELL_VIA=next+laravel-session-gate");
});
