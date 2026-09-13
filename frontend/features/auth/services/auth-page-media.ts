import { fetchManagedPage } from "@/features/public-content/utils/laravel-api";
import { AUTH_ILLUSTRATION_FALLBACK } from "../constants/auth-media";

export type PageMediaImage = {
  url: string;
  alt: string;
};

export async function getAuthIllustrationMedia(): Promise<PageMediaImage> {
  const remote = await fetchManagedPage("login");
  const cms = remote?.media?.auth_illustration;

  if (cms?.url) {
    return {
      url: cms.url,
      alt: cms.alt ?? "",
    };
  }

  return {
    url: AUTH_ILLUSTRATION_FALLBACK,
    alt: "",
  };
}
