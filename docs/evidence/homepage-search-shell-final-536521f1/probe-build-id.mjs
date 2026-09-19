const res = await fetch("https://jetpakistan.pk");
const html = await res.text();
const nextData = html.match(/<script id="__NEXT_DATA__"[^>]*>([\s\S]*?)<\/script>/);
if (nextData) {
  const data = JSON.parse(nextData[1]);
  console.log("BUILD_ID from __NEXT_DATA__:", data.buildId);
}
const scripts = [...html.matchAll(/_next\/static\/([A-Za-z0-9_-]+)\//g)].map((m) => m[1]);
const filtered = [...new Set(scripts)].filter((x) => !["chunks", "css", "media"].includes(x));
console.log("script build segments:", filtered);
console.log("meta:", html.match(/name="x-next-build-id"\s+content="([^"]+)"/)?.[1]);
console.log("data-build:", html.match(/data-build-id="([^"]+)"/)?.[1]);
