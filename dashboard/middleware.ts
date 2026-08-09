import { NextResponse, type NextRequest } from "next/server";

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const portal = pathname.startsWith("/staff/dashboard")
    ? "staff"
    : pathname.startsWith("/admin/dashboard")
      ? "admin"
      : null;

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-dashboard-pathname", pathname);
  if (portal) {
    requestHeaders.set("x-dashboard-portal", portal);
  }

  return NextResponse.next({
    request: {
      headers: requestHeaders,
    },
  });
}

export const config = {
  matcher: ["/admin/dashboard/:path*", "/staff/dashboard/:path*"],
};
