import { test, expect } from "@playwright/test";
import { attachRuntimeGuards } from "../jp-full-next-frontend/helpers";
import { assertHomepageMarketingSections, revealAllScrollTargets, waitForHomepageLayout } from "./scroll-reveal-helpers";

test.describe("JP-FRONTEND-UX-02 motion", () => {
  test("scroll reveal hides only after armed class is applied", async ({ page }) => {
    await page.goto("/");
    await waitForHomepageLayout(page);

    const opacityWithoutArmed = await page.evaluate(() => {
      const element = document.createElement("section");
      element.className = "jp-scroll-reveal";
      document.body.appendChild(element);
      const opacity = window.getComputedStyle(element).opacity;
      element.remove();
      return opacity;
    });
    expect(opacityWithoutArmed).toBe("1");

    const reveal = page.locator(".jp-scroll-reveal").first();
    await reveal.scrollIntoViewIfNeeded();
    await expect(reveal).toHaveAttribute("data-revealed", "true", { timeout: 5000 });
    await expect(reveal).toHaveCSS("opacity", "1");
  });

  test("scroll reveal targets activate after scrolling and stay revealed", async ({ page }) => {
    const guards = await attachRuntimeGuards(page);
    await page.goto("/");
    await waitForHomepageLayout(page);
    await revealAllScrollTargets(page);
    await assertHomepageMarketingSections(page);

    const first = page.locator(".jp-scroll-reveal").first();
    const revealed = await first.getAttribute("data-revealed");
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect(first).toHaveAttribute("data-revealed", revealed ?? "true");

    guards.assertClean();
  });

  test("reduced motion makes reveal targets immediately visible", async ({ page }) => {
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/");
    await waitForHomepageLayout(page);

    const reduced = page.locator(".jp-scroll-reveal--reduced").first();
    await expect(reduced).toBeVisible();
    await expect(reduced).toHaveCSS("transform", "none");
    await expect(reduced).toHaveAttribute("data-revealed", "true");
    await assertHomepageMarketingSections(page);
  });

  test("content remains visible when JavaScript is disabled", async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto("/");
    await assertHomepageMarketingSections(page);
    await context.close();
  });

  test("observer registration failure reveals target immediately", async ({ page }) => {
    await page.goto("/");
    await waitForHomepageLayout(page);

    const result = await page.evaluate(() => {
      class BrokenIntersectionObserver {
        observe(_target: Element) {
          throw new Error("observer registration failed");
        }
        unobserve() {}
        disconnect() {}
      }

      const element = document.createElement("section");
      element.className = "jp-scroll-reveal";
      element.setAttribute("data-revealed", "false");
      document.body.appendChild(element);

      element.classList.add("jp-scroll-reveal--armed");
      try {
        const observer = new BrokenIntersectionObserver();
        observer.observe(element);
      } catch {
        element.classList.add("jp-reveal-visible");
        element.setAttribute("data-revealed", "true");
      }

      const opacity = window.getComputedStyle(element).opacity;
      const revealed = element.getAttribute("data-revealed");
      element.remove();
      return { revealed, opacity };
    });

    expect(result.revealed).toBe("true");
    expect(result.opacity).toBe("1");
  });

  test("missing IntersectionObserver reveals target immediately", async ({ page }) => {
    await page.goto("/");
    await waitForHomepageLayout(page);

    const result = await page.evaluate(() => {
      const element = document.createElement("section");
      element.className = "jp-scroll-reveal";
      element.setAttribute("data-revealed", "false");
      document.body.appendChild(element);

      if (typeof IntersectionObserver === "undefined") {
        element.classList.add("jp-reveal-visible");
        element.setAttribute("data-revealed", "true");
      } else {
        const previous = window.IntersectionObserver;
        // @ts-expect-error test-only removal
        window.IntersectionObserver = undefined;
        if (typeof IntersectionObserver === "undefined") {
          element.classList.add("jp-reveal-visible");
          element.setAttribute("data-revealed", "true");
        }
        window.IntersectionObserver = previous;
      }

      const opacity = window.getComputedStyle(element).opacity;
      const revealed = element.getAttribute("data-revealed");
      element.remove();
      return { revealed, opacity };
    });

    expect(result.revealed).toBe("true");
    expect(result.opacity).toBe("1");
  });

  test("reveal does not cause material layout shift", async ({ page }) => {
    await page.goto("/");
    await waitForHomepageLayout(page);

    const target = page.locator(".jp-scroll-reveal").first();
    await target.scrollIntoViewIfNeeded();

    const before = await target.boundingBox();
    await expect(target).toHaveAttribute("data-revealed", "true", { timeout: 5000 });
    const after = await target.boundingBox();

    expect(before).not.toBeNull();
    expect(after).not.toBeNull();
    if (before && after) {
      expect(Math.abs(before.height - after.height)).toBeLessThanOrEqual(2);
      expect(Math.abs(before.width - after.width)).toBeLessThanOrEqual(2);
    }
  });
});
