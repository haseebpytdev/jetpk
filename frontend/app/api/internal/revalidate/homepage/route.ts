import { revalidateTag } from "next/cache";
import { NextRequest, NextResponse } from "next/server";

const REVALIDATE_TAGS = ["homepage-cms", "public-config"] as const;

export async function POST(request: NextRequest): Promise<NextResponse> {
  const expected = process.env.JETPK_NEXT_REVALIDATE_SECRET?.trim();
  const provided = request.headers.get("x-jetpk-revalidate-secret")?.trim();

  if (expected === "" || expected === undefined || provided !== expected) {
    return NextResponse.json({ ok: false, message: "Unauthorized." }, { status: 401 });
  }

  for (const tag of REVALIDATE_TAGS) {
    revalidateTag(tag);
  }

  return NextResponse.json({
    ok: true,
    revalidated: [...REVALIDATE_TAGS],
    at: new Date().toISOString(),
  });
}
