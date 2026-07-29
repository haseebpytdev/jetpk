import type { Metadata } from "next";
import { appConfig } from "@/lib/config";
import type { PublicSeo } from "../types";

function normalizePath(path: string): string {
  if (!path.startsWith("/")) {
    return `/${path}`;
  }

  return path;
}

function resolveCanonicalUrl(seo: PublicSeo, fallbackPath?: string): string | undefined {
  const candidate = seo.canonical?.trim();
  if (candidate) {
    if (candidate.startsWith("http://") || candidate.startsWith("https://")) {
      try {
        const url = new URL(candidate);
        const appHost = new URL(appConfig.appUrl).host;
        if (url.host === appHost) {
          return url.toString();
        }
      } catch {
        return undefined;
      }

      return undefined;
    }

    return new URL(normalizePath(candidate), appConfig.appUrl).toString();
  }

  if (fallbackPath) {
    return new URL(normalizePath(fallbackPath), appConfig.appUrl).toString();
  }

  return undefined;
}

export function publicSeoToMetadata(seo: PublicSeo, fallbackPath?: string): Metadata {
  const canonical = resolveCanonicalUrl(seo, fallbackPath);
  const ogImage = seo.og_image?.trim();

  return {
    title: seo.title,
    description: seo.description,
    robots: seo.robots,
    alternates: canonical ? { canonical } : undefined,
    openGraph: {
      title: seo.og_title || seo.title,
      description: seo.og_description || seo.description,
      url: canonical,
      images: ogImage ? [{ url: ogImage }] : undefined,
      siteName: "JetPakistan",
      type: "website",
    },
    twitter: {
      card: ogImage ? "summary_large_image" : "summary",
      title: seo.og_title || seo.title,
      description: seo.og_description || seo.description,
      images: ogImage ? [ogImage] : undefined,
    },
  };
}

export function noIndexMetadata(title: string, description?: string): Metadata {
  return {
    title,
    description,
    robots: { index: false, follow: false },
  };
}
