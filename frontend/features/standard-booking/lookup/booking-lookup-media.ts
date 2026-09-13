import { AUTH_ILLUSTRATION_FALLBACK } from "@/features/auth/constants/auth-media";
import { fetchManagedPage } from "@/features/public-content/utils/laravel-api";
import type { PageMediaImage } from "@/features/auth/services/auth-page-media";

export async function getBookingLookupHeroMedia(): Promise<PageMediaImage> {
  const remote = await fetchManagedPage("booking-lookup");
  const cms = remote?.media?.booking_lookup_hero;

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
