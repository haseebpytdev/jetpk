import { revalidatePath, revalidateTag } from "next/cache";
import { NextRequest, NextResponse } from "next/server";
import { PUBLIC_CACHE_TAGS } from "@/lib/public-cache-tags";

type RevalidateBody = {
  page_keys?: string[];
  paths?: string[];
  cms_slugs?: string[];
  global?: boolean;
  homepage?: boolean;
  sitemap?: boolean;
};

const PUBLIC_HOMEPAGE_TAG = "public-homepage";
/** @deprecated Intentional alias until all publishers migrate — see PublicCacheTags. */
const LEGACY_HOMEPAGE_TAG = "homepage-cms";

function uniquePush(list: string[], value: string): void {
  if (!list.includes(value)) {
    list.push(value);
  }
}

export async function POST(request: NextRequest): Promise<NextResponse> {
  const expected = process.env.JETPK_NEXT_REVALIDATE_SECRET?.trim();
  const provided = request.headers.get("x-jetpk-revalidate-secret")?.trim();

  if (expected === "" || expected === undefined || provided !== expected) {
    return NextResponse.json({ ok: false, message: "Unauthorized." }, { status: 401 });
  }

  const body = (await request.json().catch(() => ({}))) as RevalidateBody;
  const revalidatedTags: string[] = [];
  const revalidatedPaths: string[] = [];

  if (body.global) {
    revalidateTag("public-config");
    uniquePush(revalidatedTags, "public-config");
    revalidateTag(PUBLIC_CACHE_TAGS.content);
    uniquePush(revalidatedTags, PUBLIC_CACHE_TAGS.content);
    revalidateTag(PUBLIC_CACHE_TAGS.config);
    uniquePush(revalidatedTags, PUBLIC_CACHE_TAGS.config);
    revalidatePath("/", "layout");
    uniquePush(revalidatedPaths, "/");
  }

  if (body.homepage) {
    revalidateTag(PUBLIC_HOMEPAGE_TAG);
    uniquePush(revalidatedTags, PUBLIC_HOMEPAGE_TAG);
    revalidateTag(LEGACY_HOMEPAGE_TAG);
    uniquePush(revalidatedTags, LEGACY_HOMEPAGE_TAG);
    revalidateTag("public-cms");
    uniquePush(revalidatedTags, "public-cms");
    revalidateTag(PUBLIC_CACHE_TAGS.content);
    uniquePush(revalidatedTags, PUBLIC_CACHE_TAGS.content);
    revalidateTag(PUBLIC_CACHE_TAGS.page("home"));
    uniquePush(revalidatedTags, PUBLIC_CACHE_TAGS.page("home"));
    revalidatePath("/", "layout");
    uniquePush(revalidatedPaths, "/");
  }

  revalidateTag("public-seo");
  uniquePush(revalidatedTags, "public-seo");

  for (const pageKey of body.page_keys ?? []) {
    const key = pageKey.trim();
    if (key === "") continue;
    const tag = `public-seo-${key}`;
    revalidateTag(tag);
    uniquePush(revalidatedTags, tag);
    // Dual-invalidate persistent unstable_cache tags (jp-public-*).
    revalidateTag(PUBLIC_CACHE_TAGS.content);
    uniquePush(revalidatedTags, PUBLIC_CACHE_TAGS.content);
    revalidateTag(PUBLIC_CACHE_TAGS.page(key));
    uniquePush(revalidatedTags, PUBLIC_CACHE_TAGS.page(key));
  }

  for (const path of body.paths ?? []) {
    const normalized = path.trim();
    if (!normalized.startsWith("/")) continue;
    revalidatePath(normalized, "layout");
    uniquePush(revalidatedPaths, normalized);
  }

  for (const slug of body.cms_slugs ?? []) {
    const normalized = slug.trim();
    if (normalized === "") continue;
    const tag = `public-cms-${normalized}`;
    revalidateTag(tag);
    uniquePush(revalidatedTags, tag);
    const cmsPath = `/pages/${normalized}`;
    revalidatePath(cmsPath, "layout");
    uniquePush(revalidatedPaths, cmsPath);
  }

  if (body.sitemap) {
    revalidatePath("/sitemap.xml");
    uniquePush(revalidatedPaths, "/sitemap.xml");
  }

  return NextResponse.json({
    ok: true,
    revalidatedTags,
    revalidatedPaths,
    at: new Date().toISOString(),
  });
}
