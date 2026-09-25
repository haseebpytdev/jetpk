/**
 * One-shot: move root playwright*.config.* (except playwright.config.ts) into
 * tests/e2e/playwright/configs/ and rewrite relative paths to process.cwd()-rooted.
 */
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const destDir = path.join(root, 'tests/e2e/playwright/configs');
fs.mkdirSync(destDir, { recursive: true });

const keepRoot = new Set(['playwright.config.ts']);
const files = fs
  .readdirSync(root)
  .filter((f) => /^playwright.*\.config\.(ts|js|mjs|cjs)$/.test(f) && !keepRoot.has(f));

function ensurePathImport(src) {
  if (/from\s+['"]node:path['"]/.test(src) || /from\s+['"]path['"]/.test(src)) {
    return src;
  }
  if (/^import\s/m.test(src)) {
    return src.replace(/^(import\s.+?;\s*\n)/m, `$1import path from 'path';\n`);
  }
  return `import path from 'path';\n${src}`;
}

function rewrite(src) {
  let out = src;
  // testDir / outputDir / globalSetup / globalTeardown string literals
  out = out.replace(
    /\b(testDir|outputDir|globalSetup|globalTeardown)\s*:\s*['"](\.\/)?([^'"]+)['"]/g,
    (_, key, _dot, rel) => `${key}: path.join(process.cwd(), '${rel.replace(/\\/g, '/')}')`,
  );
  // reporter outputFolder sometimes
  out = out.replace(
    /outputFolder\s*:\s*['"](\.\/)?([^'"]+)['"]/g,
    (_, _dot, rel) => {
      if (rel.startsWith('playwright-report') || rel.startsWith('UI_test') || rel.startsWith('test-results')) {
        return `outputFolder: path.join(process.cwd(), '${rel.replace(/\\/g, '/')}')`;
      }
      return `outputFolder: '${rel}'`;
    },
  );
  return ensurePathImport(out);
}

const moved = [];
for (const f of files) {
  const from = path.join(root, f);
  const to = path.join(destDir, f);
  const raw = fs.readFileSync(from, 'utf8');
  const next = rewrite(raw);
  fs.writeFileSync(to, next, 'utf8');
  fs.unlinkSync(from);
  moved.push(f);
}

// Point root playwright.config.ts at consolidated specs location
const rootCfg = path.join(root, 'playwright.config.ts');
if (fs.existsSync(rootCfg)) {
  let cfg = fs.readFileSync(rootCfg, 'utf8');
  cfg = cfg.replace(
    /testDir:\s*['"]\.\/test\/e2e['"]/,
    "testDir: './tests/e2e/playwright/specs'",
  );
  fs.writeFileSync(rootCfg, cfg, 'utf8');
}

// Move test/e2e specs if present
const oldSpecDir = path.join(root, 'test/e2e');
const newSpecDir = path.join(root, 'tests/e2e/playwright/specs');
fs.mkdirSync(newSpecDir, { recursive: true });
if (fs.existsSync(oldSpecDir)) {
  for (const f of fs.readdirSync(oldSpecDir)) {
    const from = path.join(oldSpecDir, f);
    if (!fs.statSync(from).isFile()) continue;
    fs.renameSync(from, path.join(newSpecDir, f));
  }
  // remove empty dirs
  try {
    fs.rmdirSync(oldSpecDir);
    fs.rmdirSync(path.join(root, 'test'));
  } catch {
    /* keep if non-empty */
  }
}

// Update package.json scripts: -c playwright.X -> -c tests/e2e/playwright/configs/playwright.X
const pkgPath = path.join(root, 'package.json');
const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const scripts = pkg.scripts || {};
for (const [k, v] of Object.entries(scripts)) {
  if (typeof v !== 'string') continue;
  let next = v;
  next = next.replace(
    /-c\s+playwright\.([a-zA-Z0-9._-]+\.config\.(?:ts|js|mjs|cjs))/g,
    '-c tests/e2e/playwright/configs/playwright.$1',
  );
  next = next.replace(
    /playwright test test\/e2e\//g,
    'playwright test tests/e2e/playwright/specs/',
  );
  scripts[k] = next;
}
pkg.scripts = scripts;
fs.writeFileSync(pkgPath, `${JSON.stringify(pkg, null, 2)}\n`, 'utf8');

console.log(JSON.stringify({ movedCount: moved.length, moved, rootKept: [...keepRoot] }, null, 2));
