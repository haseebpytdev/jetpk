import baseConfig from "./playwright.jp-frontend-ux-02.config";
import { defineConfig } from "@playwright/test";

export default defineConfig({
  ...baseConfig,
  testDir: "./tests/jp-frontend-ux-02",
  testMatch: [/evidence-capture\.spec\.ts$/],
  testIgnore: undefined,
});
