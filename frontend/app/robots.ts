import type { MetadataRoute } from "next";
import { appConfig } from "@/lib/config";

const disallowPrivate = [
  "/customer",
  "/agent",
  "/dashboard",
  "/booking",
  "/flights/results",
  "/flights/return-options",
  "/lookup-booking",
  "/access-denied",
  "/laravel",
  "/testdash",
];

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

  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: disallowPrivate,
    },
    sitemap: `${base}/sitemap.xml`,
  };
}
