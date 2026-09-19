const fs = require("fs");
const path = process.env.TEMP + "/jp-home.html";
const h = fs.readFileSync(path, "utf8");
const ids = [];
const re = /\/_next\/static\/([A-Za-z0-9_-]+)\//g;
let m;
while ((m = re.exec(h)) !== null) {
  const id = m[1];
  if (!["css", "chunks", "media"].includes(id)) ids.push(id);
}
const uniq = [...new Set(ids)];
const b = (h.match(/"b":"([^"]+)"/) || [])[1] || null;
const buildId = (h.match(/"buildId":"([^"]+)"/) || [])[1] || null;
console.log(JSON.stringify({ uniq, b, buildId, len: h.length }, null, 2));
