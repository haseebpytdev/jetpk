import type { Metadata } from "next";
import { Suspense } from "react";
import { GroupSearchPage } from "@/features/group-ticketing";
import { CardListLoadingSkeleton } from "@/components/ui/LoadingRegion";

export const metadata: Metadata = {
  title: "Group search",
  robots: { index: false, follow: true },
};

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
