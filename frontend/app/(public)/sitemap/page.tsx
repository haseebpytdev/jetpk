import Link from "next/link";
import type { Metadata } from "next";
import { PageContainer } from "@/components/layout/PageContainer";
import { laravelApiPath } from "@/services/flight-search";

type SitemapRoute = {
  path: string;
};

async function fetchRoutes(): Promise<SitemapRoute[]> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 3_000);
  try {
    const response = await fetch(laravelApiPath("/api/public/content/sitemap-routes"), {
      headers: { Accept: "application/json" },
      next: { revalidate: 3600 },
      signal: controller.signal,
    });
    if (!response.ok) return [];
    const body = (await response.json()) as { routes?: SitemapRoute[] };
    return body.routes ?? [];
  } catch {
    return [];
  } finally {
    clearTimeout(timeout);
  }
}

export const metadata: Metadata = {
  title: "Sitemap",
  description: "Browse all public JetPakistan pages.",
  robots: "index,follow",
};

/** Keep HTML sitemap out of SSG — Laravel route list can hang build workers. */
export const dynamic = "force-dynamic";

export default async function HtmlSitemapPage() {
  const routes = await fetchRoutes();

  return (
    <PageContainer className="py-jp-4xl">
      <h1 className="font-sans text-jp-h2 font-semibold text-jp-text">Sitemap</h1>
      <p className="mt-2 text-jp-sm text-jp-muted">Authoritative public routes served by JetPakistan.</p>
      <ul className="mt-jp-xl grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {routes.map((route) => (
          <li key={route.path}>
            <Link href={route.path} className="text-jp-sm text-jp-primary hover:underline focus-visible:shadow-jp-focus">
              {route.path}
            </Link>
          </li>
        ))}
      </ul>
    </PageContainer>
  );
}
