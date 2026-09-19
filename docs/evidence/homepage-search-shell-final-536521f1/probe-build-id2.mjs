const res = await fetch("https://jetpakistan.pk");
const html = await res.text();
console.log("html length", html.length);
const patterns = [
  /buildId['":\s]+['"]([A-Za-z0-9_-]+)['"]/g,
  /BUILD_ID['":\s]+['"]([A-Za-z0-9_-]+)['"]/g,
  /_next\/static\/([A-Za-z0-9_-]{10,})\//g,
  /\/_next\/data\/([A-Za-z0-9_-]+)\//g,
];
for (const p of patterns) {
  const matches = [...html.matchAll(p)].map((m) => m[1]);
  if (matches.length) console.log(p.toString(), [...new Set(matches)]);
}
console.log("has __NEXT_DATA__", html.includes("__NEXT_DATA__"));
console.log("sample scripts", [...html.matchAll(/<script[^>]+src="([^"]+)"/g)].slice(0,8).map(m=>m[1]));
