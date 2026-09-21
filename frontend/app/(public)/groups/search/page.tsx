import type { Metadata } from "next";
import { Suspense } from "react";
import { GroupSearchPage } from "@/features/group-ticketing";
import { CardListLoadingSkeleton } from "@/components/ui/LoadingRegion";
import { noIndexMetadata } from "@/features/public-content";

export const metadata: Metadata = noIndexMetadata("Group search — JetPakistan", {
  description: "Search JetPakistan group ticketing packages. Utility page — not a search landing.",
  path: "/groups/search",
  follow: true,
});

/** Soft-nav: Suspense around useSearchParams so loading UI + URL commit are not blocked. */
export default function Page() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-jp p-4">
          <CardListLoadingSkeleton label="Loading group packages" />
        </div>
      }
    >
      <GroupSearchPage />
    </Suspense>
  );
}
