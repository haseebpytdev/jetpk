import type { MetadataRoute } from "next";
import { appConfig } from "@/lib/config";

/**
 * Private / transactional paths — never open these for AI or search crawlers.
 * Keep in sync with public/robots.txt and docs/closure/SEO-AEO-GEO/06-ai-crawler-policy.md.
 */
const disallowPrivate = [
  "/customer",
  "/agent",
  "/admin",
  "/staff",
  "/dashboard",
  "/booking",
  "/flights/results",
  "/flights/return-options",
  "/flights/fare-selection",
  "/flights/s",
  "/lookup-booking",
  "/groups/search",
  "/b",
  "/g",
  "/v",
  "/l",
  "/access-denied",
  "/login/otp",
  "/reset-password",
  "/verify-email",
  "/forgot-password",
  "/api",
  "/dev",
  "/ui",
  "/laravel",
  "/testdash",
];

/** Named AI discovery bots: same public Allow + private Disallow as User-agent: *. */
const aiDiscoveryAgents = ["GPTBot", "ClaudeBot", "Google-Extended"] as const;

export default function robots(): MetadataRoute.Robots {
  const base = appConfig.appUrl.replace(/\/$/, "");
  const isProduction = process.env.NODE_ENV === "production";

  if (!isProduction) {
    return {
      rules: {
        userAgent: "*",
        disallow: "/",
      },
    };
  }

  const publicRule = {
    allow: "/",
    disallow: disallowPrivate,
  };

  return {
    rules: [
      {
        userAgent: "*",
        ...publicRule,
      },
      // Intentional: discovery/citation crawlers may index public SEO pages;
      // private disallows are unchanged (do not open /admin, /customer, etc.).
      ...aiDiscoveryAgents.map((userAgent) => ({
        userAgent,
        ...publicRule,
      })),
    ],
    sitemap: `${base}/sitemap.xml`,
  };
}
