import type { NextRequest } from "next/server";
import { NextResponse } from "next/server";

function shouldForceHttps(request: NextRequest): boolean {
  if (process.env.NODE_ENV !== "production") {
    return false;
  }

  const host = request.headers.get("host") ?? "";
  if (host.includes("localhost") || host.startsWith("127.0.0.1")) {
    return false;
  }

  const forwardedProto = request.headers.get("x-forwarded-proto");
  if (forwardedProto) {
    return forwardedProto !== "https";
  }

  return request.nextUrl.protocol === "http:";
}

function canonicalHttpsOrigin(request: NextRequest): string {
  const configured = process.env.NEXT_PUBLIC_APP_URL?.replace(/\/$/, "");
  if (configured) {
    return configured.startsWith("http") ? configured : `https://${configured}`;
  }

  const forwardedHost = request.headers.get("x-forwarded-host")?.split(",")[0]?.trim();
  if (forwardedHost && !forwardedHost.includes("localhost") && !forwardedHost.startsWith("127.")) {
    return `https://${forwardedHost}`;
  }

  const host = request.headers.get("host") ?? "";
  if (host && !host.includes("localhost") && !host.startsWith("127.")) {
    return `https://${host}`;
  }

  const url = request.nextUrl.clone();
  url.protocol = "https:";
  return url.origin;
}

export function middleware(request: NextRequest) {
  if (!shouldForceHttps(request)) {
    return NextResponse.next();
  }

  const target = new URL(`${request.nextUrl.pathname}${request.nextUrl.search}`, canonicalHttpsOrigin(request));
  return NextResponse.redirect(target, 308);
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
