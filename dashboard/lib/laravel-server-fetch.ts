import { cookies } from "next/headers";

export function buildCookieHeader(cookieList: Array<{ name: string; value: string }>): string {
  return cookieList.map((cookie) => `${cookie.name}=${cookie.value}`).join("; ");
}

export function getLaravelServerBase(): string {
  return (
    process.env.LARAVEL_URL ??
    process.env.NEXT_PUBLIC_LARAVEL_URL ??
    "http://127.0.0.1:8088"
  ).replace(/\/$/, "");
}

export async function getServerCookieHeader(): Promise<string | undefined> {
  const cookieStore = await cookies();
  const all = cookieStore.getAll();
  if (all.length === 0) {
    return undefined;
  }

  return buildCookieHeader(all);
}

export async function fetchLaravelServer(path: string, init?: RequestInit): Promise<Response> {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  const url = `${getLaravelServerBase()}${normalized}`;
  const cookieHeader = await getServerCookieHeader();

  const headers = new Headers(init?.headers ?? {});
  headers.set("Accept", "application/json");
  headers.set("X-Requested-With", "XMLHttpRequest");
  if (cookieHeader) {
    headers.set("Cookie", cookieHeader);
  }

  return fetch(url, {
    ...init,
    headers,
    cache: "no-store",
  });
}
