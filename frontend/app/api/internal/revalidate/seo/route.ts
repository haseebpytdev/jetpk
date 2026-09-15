import { revalidatePath, revalidateTag } from "next/cache";
import { NextRequest, NextResponse } from "next/server";

type RevalidateBody = {
  page_keys?: string[];
  paths?: string[];
  cms_slugs?: string[];
  global?: boolean;
  sitemap?: boolean;
};

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
    revalidatedTags.push("public-config");
    revalidatePath("/");
    revalidatedPaths.push("/");
  }

  revalidateTag("public-seo");
  revalidatedTags.push("public-seo");

  for (const pageKey of body.page_keys ?? []) {
    const key = pageKey.trim();
    if (key === "") continue;
    const tag = `public-seo-${key}`;
    revalidateTag(tag);
    revalidatedTags.push(tag);
  }

  for (const path of body.paths ?? []) {
    const normalized = path.trim();
    if (!normalized.startsWith("/")) continue;
    revalidatePath(normalized);
    revalidatedPaths.push(normalized);
  }

  for (const slug of body.cms_slugs ?? []) {
    const normalized = slug.trim();
    if (normalized === "") continue;
    const tag = `public-cms-${normalized}`;
    revalidateTag(tag);
    revalidatedTags.push(tag);
    const cmsPath = `/pages/${normalized}`;
    revalidatePath(cmsPath);
    revalidatedPaths.push(cmsPath);
  }

  if (body.sitemap) {
    revalidatePath("/sitemap.xml");
    revalidatedPaths.push("/sitemap.xml");
  }

  return NextResponse.json({
    ok: true,
    revalidatedTags,
    revalidatedPaths,
    at: new Date().toISOString(),
  });
}
