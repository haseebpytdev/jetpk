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
    title: {
      absolute: seo.title,
    },
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

type NoIndexOptions = {
  description?: string;
  /** Canonical path (same-site). Utilities should still expose a stable canonical. */
  path?: string;
  /** Default false (dashboards). Public utilities use follow: true per SEO catalog. */
  follow?: boolean;
};

/**
 * Non-indexable metadata. Prefer this over bare `title` strings so utilities still
 * emit absolute titles + canonical/OG without entering the sitemap.
 */
export function noIndexMetadata(title: string, descriptionOrOptions?: string | NoIndexOptions): Metadata {
  const options: NoIndexOptions =
    typeof descriptionOrOptions === "string"
      ? { description: descriptionOrOptions }
      : (descriptionOrOptions ?? {});
  const follow = options.follow ?? false;
  const canonical = options.path
    ? new URL(normalizePath(options.path), appConfig.appUrl).toString()
    : undefined;

  return {
    title: { absolute: title },
    description: options.description,
    robots: { index: false, follow },
    alternates: canonical ? { canonical } : undefined,
    openGraph: {
      title,
      description: options.description,
      url: canonical,
      siteName: "JetPakistan",
      type: "website",
    },
    twitter: {
      card: "summary",
      title,
      description: options.description,
    },
  };
}
