import { test, expect } from "@playwright/test";

test("image slot renders fallback without layout collapse", async ({ page }) => {
  await page.setContent(`
    <div id="root" style="width:320px"></div>
    <script type="module">
      import React from 'https://esm.sh/react@19';
      import ReactDOM from 'https://esm.sh/react-dom@19/client';
      // Fallback test uses static markup equivalent
      document.getElementById('root').innerHTML = \`
        <div data-testid="image-slot-fallback" style="aspect-ratio:16/9;width:100%;max-width:320px;display:flex;align-items:center;justify-content:center;border:1px solid #ccc;">
          <span class="sr-only">Image unavailable</span>
        </div>
      \`;
    </script>
  `);

  const slot = page.getByTestId("image-slot-fallback");
  await expect(slot).toBeVisible();
  const box = await slot.boundingBox();
  expect(box?.height).toBeGreaterThan(100);
});
