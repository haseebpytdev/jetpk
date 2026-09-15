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

export function middleware(request: NextRequest) {
  if (!shouldForceHttps(request)) {
    return NextResponse.next();
  }

  const url = request.nextUrl.clone();
  url.protocol = "https:";

  return NextResponse.redirect(url, 308);
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
