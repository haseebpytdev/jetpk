# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: dashboard-role-pages.spec.ts >> role-customer dashboard pages >> GET /customer/bookings
- Location: tests\playwright\jetpk-9h-b\dashboard-role-pages.spec.ts:25:7

# Error details

```
Error: Broken images on /customer/bookings: http://jetpk.test/storage/agencies/1/branding/jetpk-qa-9hb.png
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - generic [ref=e3]:
      - link "Asif Travels portal" [ref=e4] [cursor=pointer]:
        - /url: /customer
        - img "Asif Travels" [ref=e5]
      - generic [ref=e6]:
        - link "Public site" [ref=e7] [cursor=pointer]:
          - /url: /
        - link "Profile" [ref=e8] [cursor=pointer]:
          - /url: /profile
  - generic [ref=e9]:
    - complementary "Portal navigation" [ref=e10]:
      - generic [ref=e11]:
        - generic [ref=e12]: J
        - generic [ref=e13]:
          - generic [ref=e14]: My account
          - strong [ref=e15]: JetPK Customer
          - generic [ref=e16]: Trips, payments, and support
      - navigation "Customer account" [ref=e17]:
        - link "Overview" [ref=e18] [cursor=pointer]:
          - /url: /customer
          - img [ref=e19]
          - generic [ref=e21]: Overview
        - link "My trips" [ref=e22] [cursor=pointer]:
          - /url: /customer/bookings
          - img [ref=e23]
          - generic [ref=e26]: My trips
        - link "Travelers" [ref=e27] [cursor=pointer]:
          - /url: /customer/travelers
          - img [ref=e28]
          - generic [ref=e30]: Travelers
        - link "Support" [ref=e31] [cursor=pointer]:
          - /url: /customer/support/tickets
          - img [ref=e32]
          - generic [ref=e34]: Support
        - link "Search flights" [ref=e35] [cursor=pointer]:
          - /url: /flights/search
          - img [ref=e36]
          - generic [ref=e39]: Search flights
    - main [ref=e40]:
      - generic [ref=e41]:
        - generic [ref=e42]:
          - heading "My bookings" [level=1] [ref=e43]
          - paragraph [ref=e44]: View and manage your flight requests and confirmations.
        - link "Search flights" [ref=e45] [cursor=pointer]:
          - /url: /flights/search
      - generic [ref=e46]:
        - link "All" [ref=e47] [cursor=pointer]:
          - /url: /customer/bookings?filter=all
        - link "Pending payment" [ref=e48] [cursor=pointer]:
          - /url: /customer/bookings?filter=pending_payment
        - link "PNR created" [ref=e49] [cursor=pointer]:
          - /url: /customer/bookings?filter=pnr_created
        - link "Needs action" [ref=e50] [cursor=pointer]:
          - /url: /customer/bookings?filter=needs_action
        - link "Cancelled" [ref=e51] [cursor=pointer]:
          - /url: /customer/bookings?filter=cancelled
      - generic [ref=e54]:
        - paragraph [ref=e55]: No bookings found
        - paragraph [ref=e56]: Try another filter or search for new flights.
        - paragraph [ref=e57]:
          - link "Search flights" [ref=e58] [cursor=pointer]:
            - /url: /flights/search
```

# Test source

```ts
  28  |       if (
  29  |         text.includes('net::ERR_CONNECTION') ||
  30  |         text.includes('net::ERR_NETWORK_CHANGED') ||
  31  |         text.includes('Failed to load resource: net::')
  32  |       ) {
  33  |         return;
  34  |       }
  35  |       consoleErrors.push(text);
  36  |     }
  37  |   });
  38  | 
  39  |   page.on('response', (response) => {
  40  |     const url = response.url();
  41  |     try {
  42  |       const pageOrigin = new URL(page.url()).origin;
  43  |       if (!url.startsWith(pageOrigin)) {
  44  |         return;
  45  |       }
  46  |     } catch {
  47  |       return;
  48  |     }
  49  |     const status = response.status();
  50  |     if (status >= 500 && !url.includes('favicon')) {
  51  |       networkFailures.push({ url, status });
  52  |     }
  53  |   });
  54  | 
  55  |   return { consoleErrors, networkFailures };
  56  | }
  57  | 
  58  | export async function assertDashboardPage(page: Page, spec: PageSpec, testInfo: TestInfo): Promise<void> {
  59  |   const { consoleErrors, networkFailures } = collectPageSignals(page);
  60  |   const response = await page.goto(spec.path, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  61  |   const status = response?.status() ?? 0;
  62  |   const allowed = Array.isArray(spec.expectStatus) ? spec.expectStatus : [spec.expectStatus ?? 200];
  63  | 
  64  |   if (!allowed.includes(status)) {
  65  |     throw new Error(`Unexpected HTTP ${status} for ${spec.path}`);
  66  |   }
  67  | 
  68  |   await page.locator('body').waitFor({ state: 'visible' });
  69  | 
  70  |   await page
  71  |     .waitForFunction(
  72  |       () => {
  73  |         const images = Array.from(document.images).filter((img) => img.src && !img.src.toLowerCase().includes('.svg'));
  74  |         return images.every((img) => img.complete);
  75  |       },
  76  |       undefined,
  77  |       { timeout: 15_000 },
  78  |     )
  79  |     .catch(() => {});
  80  | 
  81  |   if (spec.shell === 'auto') {
  82  |     await Promise.race([
  83  |       page.locator('#jp-dash-sidebar').first().waitFor({ state: 'visible', timeout: 15_000 }),
  84  |       page.locator('.jp-portal__top').first().waitFor({ state: 'visible', timeout: 15_000 }),
  85  |     ]);
  86  |   } else if (spec.shell) {
  87  |     await page.locator(spec.shell).first().waitFor({ state: 'visible', timeout: 15_000 });
  88  |   }
  89  | 
  90  |   if (spec.heading) {
  91  |     const heading = typeof spec.heading === 'string' ? new RegExp(spec.heading, 'i') : spec.heading;
  92  |     await page.getByRole('heading', { name: heading }).first().waitFor({ state: 'visible', timeout: 15_000 }).catch(() => {
  93  |       // Fallback: any h1 visible
  94  |       return page.locator('h1').first().waitFor({ state: 'visible', timeout: 5_000 });
  95  |     });
  96  |   }
  97  | 
  98  |   const bodyText = await page.locator('body').innerText();
  99  |   for (const leak of forbiddenBrands) {
  100 |     if (bodyText.includes(leak)) {
  101 |       throw new Error(`Forbidden branding leak "${leak}" on ${spec.path}`);
  102 |     }
  103 |   }
  104 | 
  105 |   const overflow = await page.evaluate(() => {
  106 |     const doc = document.documentElement;
  107 |     const body = document.body;
  108 |     return doc.scrollWidth > doc.clientWidth + 2 || body.scrollWidth > body.clientWidth + 2;
  109 |   });
  110 |   if (overflow) {
  111 |     throw new Error(`Horizontal overflow on ${spec.path}`);
  112 |   }
  113 | 
  114 |   const brokenImages = await page.evaluate(() =>
  115 |     Array.from(document.images)
  116 |       .filter((img) => {
  117 |         if (!img.src) {
  118 |           return false;
  119 |         }
  120 |         if (img.src.toLowerCase().includes('.svg')) {
  121 |           return false;
  122 |         }
  123 |         return img.naturalWidth === 0 && img.naturalHeight === 0;
  124 |       })
  125 |       .map((img) => img.src),
  126 |   );
  127 |   if (brokenImages.length > 0) {
> 128 |     throw new Error(`Broken images on ${spec.path}: ${brokenImages.join(', ')}`);
      |           ^ Error: Broken images on /customer/bookings: http://jetpk.test/storage/agencies/1/branding/jetpk-qa-9hb.png
  129 |   }
  130 | 
  131 |   const shotDir = path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-b', 'screenshots', testInfo.project.name);
  132 |   fs.mkdirSync(shotDir, { recursive: true });
  133 |   const safeName = spec.path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'root';
  134 |   await page.screenshot({ path: path.join(shotDir, `${safeName}.png`), fullPage: true });
  135 | 
  136 |   const report = {
  137 |     path: spec.path,
  138 |     project: testInfo.project.name,
  139 |     status,
  140 |     consoleErrors,
  141 |     networkFailures,
  142 |     brokenImages,
  143 |     horizontalOverflow: overflow,
  144 |   };
  145 | 
  146 |   fs.mkdirSync(auditDir, { recursive: true });
  147 |   fs.appendFileSync(path.join(auditDir, 'page-results.jsonl'), `${JSON.stringify(report)}\n`);
  148 | 
  149 |   if (consoleErrors.length > 0) {
  150 |     throw new Error(`Console errors on ${spec.path}: ${consoleErrors.join(' | ')}`);
  151 |   }
  152 |   if (networkFailures.some((f) => f.status >= 500)) {
  153 |     throw new Error(`5xx network failures on ${spec.path}`);
  154 |   }
  155 | }
  156 | 
  157 | export const adminPages: PageSpec[] = [
  158 |   { path: '/admin', shell: '#jp-dash-sidebar' },
  159 |   { path: '/admin/bookings', shell: '#jp-dash-sidebar' },
  160 |   { path: '/admin/customers', shell: '#jp-dash-sidebar' },
  161 |   { path: '/admin/users', shell: '#jp-dash-sidebar' },
  162 |   { path: '/admin/agents', shell: '#jp-dash-sidebar' },
  163 |   { path: '/admin/api-settings', shell: '#jp-dash-sidebar' },
  164 |   { path: '/admin/api-settings/create?provider=sabre', shell: '#jp-dash-sidebar' },
  165 |   { path: '/admin/api-settings/create?provider=pia_ndc', shell: '#jp-dash-sidebar' },
  166 |   { path: '/admin/api-settings/create?provider=airblue', shell: '#jp-dash-sidebar' },
  167 |   { path: '/admin/group-ticketing', shell: '#jp-dash-sidebar' },
  168 |   { path: '/admin/reports', shell: '#jp-dash-sidebar' },
  169 |   { path: '/admin/accounting/ledger', shell: '#jp-dash-sidebar' },
  170 |   { path: '/admin/ledger', shell: '#jp-dash-sidebar' },
  171 |   { path: '/admin/markups', shell: '#jp-dash-sidebar' },
  172 |   { path: '/admin/support/tickets', shell: '#jp-dash-sidebar' },
  173 |   { path: '/admin/settings/communications', shell: '#jp-dash-sidebar' },
  174 |   { path: '/admin/reports/supplier-diagnostics', shell: '#jp-dash-sidebar' },
  175 |   { path: '/admin/settings', shell: '#jp-dash-sidebar' },
  176 |   { path: '/admin/settings/branding', shell: '#jp-dash-sidebar' },
  177 |   { path: '/admin/settings/media', shell: '#jp-dash-sidebar' },
  178 |   { path: '/admin/page-settings', shell: '#jp-dash-sidebar' },
  179 |   { path: '/admin/page-settings/home', shell: '#jp-dash-sidebar' },
  180 |   { path: '/profile', shell: 'auto' },
  181 | ];
  182 | 
  183 | export const staffPages: PageSpec[] = [
  184 |   { path: '/staff', shell: '#jp-dash-sidebar' },
  185 |   { path: '/staff/bookings', shell: '#jp-dash-sidebar' },
  186 |   { path: '/profile', shell: 'auto' },
  187 | ];
  188 | 
  189 | export const agentPages: PageSpec[] = [
  190 |   { path: '/agent', shell: '.jp-portal__top' },
  191 |   { path: '/agent/bookings', shell: '.jp-portal__top' },
  192 |   { path: '/agent/agency', shell: '.jp-portal__top' },
  193 |   { path: '/profile', shell: 'auto' },
  194 | ];
  195 | 
  196 | export const customerPages: PageSpec[] = [
  197 |   { path: '/customer', shell: '.jp-portal__top' },
  198 |   { path: '/customer/bookings', shell: '.jp-portal__top' },
  199 |   { path: '/customer/support', shell: '.jp-portal__top' },
  200 |   { path: '/profile', shell: 'auto' },
  201 | ];
  202 | 
```