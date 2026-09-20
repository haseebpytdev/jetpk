import { cache } from "react";
import { fetchManagedPage } from "../utils/laravel-api";
import type { SupportPageContent } from "../types";
import { resolveSupportPage } from "./support-content-browser";

export { resolveSupportPage, loadSupportPageBrowser } from "./support-content-browser";

export const SupportContentService = {
  getSupportPage: cache(async (): Promise<SupportPageContent> => {
    return resolveSupportPage(await fetchManagedPage("support"));
  }),
};
