import { revalidatePath, revalidateTag } from "next/cache";
import { NextRequest, NextResponse } from "next/server";
import {
  PUBLIC_CACHE_TAGS,
  PUBLIC_MANAGED_PAGE_KEYS,
  PUBLIC_REVALIDATE_PATH_ALLOWLIST,
} from "@/lib/public-cache-tags";

type RevalidateBody = {
  /** Allowlisted managed page keys only. */
  page_keys?: string[];
  /** Allowlisted public paths only (must start with /). */
  paths?: string[];
  /** Invalidate jp-public-config (+ shared content root). */
  config?: boolean;
  /** Invalidate all persistent public content (root tag). */
  global?: boolean;
  homepage?: boolean;
  sitemap?: boolean;
  /** Explicit allowlisted tags only (jp-public-*). */
  tags?: string[];
};

const PAGE_KEY_SET = new Set<string>(PUBLIC_MANAGED_PAGE_KEYS);
const PATH_SET = new Set<string>(PUBLIC_REVALIDATE_PATH_ALLOWLIST);

function isAllowlistedTag(tag: string): boolean {
  if (tag === PUBLIC_CACHE_TAGS.content || tag === PUBLIC_CACHE_TAGS.config) {
    return true;
  }
  if (tag.startsWith("jp-public-page-")) {
    const key = tag.slice("jp-public-page-".length);
    return PAGE_KEY_SET.has(key);
  }
  return false;
}

function uniquePush(list: string[], value: string): void {
  if (!list.includes(value)) {
    list.push(value);
  }
}

/**
 * Protected CMS public-content cache invalidation.
 * POST only · shared secret · page/tag/path allowlists · no open revalidation.
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  const expected = process.env.JETPK_NEXT_REVALIDATE_SECRET?.trim();
  const provided = request.headers.get("x-jetpk-revalidate-secret")?.trim();

  if (expected === "" || expected === undefined || provided !== expected) {
    return NextResponse.json({ ok: false, message: "Unauthorized." }, { status: 401 });
  }

  const body = (await request.json().catch(() => ({}))) as RevalidateBody;
  const revalidatedTags: string[] = [];
  const revalidatedPaths: string[] = [];
  const rejected: { page_keys?: string[]; paths?: string[]; tags?: string[] } = {};

  const invalidateTag = (tag: string): void => {
    revalidateTag(tag);
    uniquePush(revalidatedTags, tag);
  };

  const invalidatePath = (path: string): void => {
    revalidatePath(path, "layout");
    uniquePush(revalidatedPaths, path);
  };

  if (body.global || body.config || body.homepage) {
    invalidateTag(PUBLIC_CACHE_TAGS.content);
    invalidateTag(PUBLIC_CACHE_TAGS.config);
  }

  if (body.homepage) {
    invalidateTag(PUBLIC_CACHE_TAGS.page("home"));
    if (PATH_SET.has("/")) {
      invalidatePath("/");
    }
  }

  const rejectedPageKeys: string[] = [];
  for (const pageKey of body.page_keys ?? []) {
    const key = pageKey.trim();
    if (key === "") continue;
    if (!PAGE_KEY_SET.has(key)) {
      rejectedPageKeys.push(key);
      continue;
    }
    invalidateTag(PUBLIC_CACHE_TAGS.content);
    invalidateTag(PUBLIC_CACHE_TAGS.page(key));
  }
  if (rejectedPageKeys.length > 0) {
    rejected.page_keys = rejectedPageKeys;
  }

  const rejectedPaths: string[] = [];
  for (const path of body.paths ?? []) {
    const normalized = path.trim();
    if (!PATH_SET.has(normalized)) {
      if (normalized !== "") {
        rejectedPaths.push(normalized);
      }
      continue;
    }
    invalidatePath(normalized);
  }
  if (rejectedPaths.length > 0) {
    rejected.paths = rejectedPaths;
  }

  const rejectedTags: string[] = [];
  for (const tag of body.tags ?? []) {
    const normalized = tag.trim();
    if (!isAllowlistedTag(normalized)) {
      if (normalized !== "") {
        rejectedTags.push(normalized);
      }
      continue;
    }
    invalidateTag(normalized);
  }
  if (rejectedTags.length > 0) {
    rejected.tags = rejectedTags;
  }

  if (body.sitemap && PATH_SET.has("/sitemap.xml")) {
    invalidatePath("/sitemap.xml");
  }

  console.info(
    JSON.stringify({
      scope: "jp-public-content",
      event: "revalidate",
      revalidatedTags,
      revalidatedPaths,
      rejected,
      at: new Date().toISOString(),
    }),
  );

  return NextResponse.json({
    ok: true,
    revalidatedTags,
    revalidatedPaths,
    rejected: Object.keys(rejected).length > 0 ? rejected : undefined,
    at: new Date().toISOString(),
  });
}

export async function GET(): Promise<NextResponse> {
  return NextResponse.json({ ok: false, message: "Method not allowed." }, { status: 405 });
}
