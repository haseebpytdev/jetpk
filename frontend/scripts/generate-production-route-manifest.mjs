import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const appRoot = path.join(__dirname, "../app");
const outPath = path.join(__dirname, "../../docs/frontend/JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json");

function walk(dir, acc = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      if (ent.name === "dev") continue;
      walk(p, acc);
    } else if (ent.name === "page.tsx") {
      acc.push(p);
    }
  }
  return acc;
}

function toRoute(pageFile) {
  let rel = path.relative(appRoot, pageFile).replace(/\\/g, "/");
  if (rel === "page.tsx") {
    return "/";
  }
  rel = rel.replace(/\/page\.tsx$/, "");
  rel = rel.replace(/\([^)]+\)\//g, "");
  return `/${rel}`;
}

function categorize(route) {
  if (["/agent", "/customer", "/booking/payment"].includes(route)) return "redirect-only";
  if (route.startsWith("/agent/")) return "agent";
  if (route.startsWith("/customer/")) return "customer";
  if (
    [
      "/login",
      "/login/otp",
      "/register",
      "/forgot-password",
      "/reset-password/[token]",
      "/password/force-change",
      "/verify-email",
      "/agent/register",
      "/agent/register/submitted",
    ].includes(route)
  ) {
    return "shared-auth";
  }
  if (
    route.startsWith("/booking/") ||
    route.startsWith("/flights/") ||
    route.startsWith("/groups/") ||
    route === "/lookup-booking" ||
    route.startsWith("/guest/")
  ) {
    return "checkout-booking";
  }
  if (
    [
      "/",
      "/about-us",
      "/faq",
      "/contact",
      "/support",
      "/terms",
      "/privacy",
      "/sitemap",
      "/[slug]",
      "/legal/[slug]",
      "/pages/[slug]",
    ].includes(route)
  ) {
    return "cms-public-content";
  }
  if (route === "/access-denied") return "utility";
  return "public";
}

const routes = walk(appRoot)
  .map((pageFile) => {
    const publicPath = toRoute(pageFile);
    const rel = path.relative(appRoot, pageFile).replace(/\\/g, "/").replace(/\/page\.tsx$/, "");
    return {
      public_path: publicPath,
      app_router_path: `frontend/app/${rel}`,
      category: categorize(publicPath),
      redirect_only: ["/agent", "/customer", "/booking/payment"].includes(publicPath),
      dynamic: publicPath.includes("["),
    };
  })
  .sort((a, b) => a.public_path.localeCompare(b.public_path));

const manifest = {
  generated_at: "2026-08-06",
  methodology:
    "Count every frontend/app/**/page.tsx once; exclude frontend/app/dev/**; dynamic segments count once; route groups omitted from public URLs; dashboard/ excluded; CMS database slugs not expanded.",
  production_route_count: routes.length,
  dev_only_route_count: 1,
  redirect_only_routes: ["/agent", "/customer", "/booking/payment"],
  routes,
};

fs.writeFileSync(outPath, `${JSON.stringify(manifest, null, 2)}\n`);
console.log(`wrote ${routes.length} routes to ${outPath}`);
