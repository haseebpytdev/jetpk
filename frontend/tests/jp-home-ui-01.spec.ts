import { expect, test } from "@playwright/test";

const viewports = [
  { width: 1440, height: 900, fullHeader: true },
  { width: 1280, height: 800, fullHeader: true },
  { width: 1024, height: 768, fullHeader: true },
  { width: 768, height: 1024, fullHeader: false },
  { width: 390, height: 844, fullHeader: false },
] as const;

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

for (const viewport of viewports) {
  test(`hero and header compose cleanly at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/", { waitUntil: "load" });

    const hero = page.getByTestId("homepage-public-hero");
    const search = page.getByTestId("search-module");
    const trustStrip = page.getByTestId("benefit-strip");
    const fab = page.getByTestId("public-fab-dock");
    const primaryNav = page.getByRole("navigation", { name: "Primary" });

    await expect(hero).toBeVisible();
    await expect(search).toBeVisible();
    await expect(trustStrip).toBeVisible();

    const geometry = await page.evaluate(() => {
      const heroElement = document.querySelector<HTMLElement>("[data-testid='homepage-public-hero']");
      const imageElement = document.querySelector<HTMLElement>("[data-testid='homepage-hero-image']");
      const trustElement = document.querySelector<HTMLElement>("[data-testid='benefit-strip']");
      const nextSection = heroElement?.nextElementSibling as HTMLElement | null;
      const heroBox = heroElement?.getBoundingClientRect();
      const imageBox = imageElement?.getBoundingClientRect();
      const trustBox = trustElement?.getBoundingClientRect();

      return {
        heroBottom: heroBox?.bottom ?? 0,
        imageBottom: imageBox?.bottom ?? 0,
        trustBottom: trustBox?.bottom ?? 0,
        layoutGap: heroElement && nextSection
          ? nextSection.offsetTop - (heroElement.offsetTop + heroElement.offsetHeight)
          : Number.POSITIVE_INFINITY,
        borderBottomWidth: heroElement ? getComputedStyle(heroElement).borderBottomWidth : "missing",
      };
    });

    expect(geometry.imageBottom).toBeGreaterThanOrEqual(geometry.trustBottom - 1);
    expect(geometry.heroBottom).toBeGreaterThanOrEqual(geometry.trustBottom - 1);
    expect(Math.abs(geometry.layoutGap)).toBeLessThanOrEqual(1);
    expect(geometry.borderBottomWidth).toBe("0px");
    await expect(page.getByTestId("homepage-hero-overlap-spacer")).toHaveCount(0);

    if (viewport.fullHeader) {
      await expect(primaryNav).toBeVisible();
      await expect(page.getByTestId("header-login-cta")).toBeVisible();
      await expect(fab).toBeHidden();
    } else {
      await expect(primaryNav).toBeHidden();
      const compactLogin = page.getByTestId("account-menu-anonymous-compact");
      await expect(compactLogin).toBeVisible();
      expect((await compactLogin.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(40);
      await expect(fab).toBeVisible();
    }
  });
}

test("Login remains keyboard accessible and routes unchanged", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/", { waitUntil: "load" });

  const login = page.getByTestId("header-login-cta");
  await expect(login).toHaveAttribute("href", "/login");
  await login.focus();
  await expect(login).toBeFocused();
  await expect(login).toHaveCSS("font-family", /Plus Jakarta Sans/i);
  expect(await login.evaluate((element) => getComputedStyle(element).boxShadow)).not.toBe("none");
});

test("FAB opens, closes with Escape, restores focus, and keeps a safe touch target", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });

  const dock = page.getByTestId("public-fab-dock");
  const trigger = page.getByTestId("public-fab-trigger");
  const triggerBox = await trigger.boundingBox();

  expect(triggerBox?.width ?? 0).toBeGreaterThanOrEqual(44);
  expect(triggerBox?.height ?? 0).toBeGreaterThanOrEqual(44);
  await trigger.click();
  await expect(dock).toHaveAttribute("open", "");
  await expect(page.getByRole("group", { name: "JetPakistan quick actions" })).toBeVisible();

  await page.keyboard.press("Escape");
  await expect(dock).not.toHaveAttribute("open", "");
  await expect(trigger).toBeFocused();
});
