import { cache } from "react";
import { fetchManagedPage } from "../utils/laravel-api";
import type { PublicPage } from "../types";
import { resolveAboutPage } from "./about-content-browser";

export { resolveAboutPage, loadAboutPageBrowser } from "./about-content-browser";

export const PublicPageService = {
  getAboutPage: cache(async (): Promise<PublicPage> => {
    return resolveAboutPage(await fetchManagedPage("about"));
  }),
};
