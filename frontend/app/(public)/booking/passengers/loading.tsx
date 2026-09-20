import { RouteLoadingSkeleton } from "@/components/ui/LoadingRegion";
import { PASSENGERS_EARLY_FETCH_INLINE } from "@/features/standard-booking/utils/passengers-early-fetch-inline";

export default function Loading() {
  return (
    <>
      <script
        id="jp-passengers-early-fetch-loading"
        dangerouslySetInnerHTML={{ __html: PASSENGERS_EARLY_FETCH_INLINE }}
      />
      <RouteLoadingSkeleton label="Loading passenger details" />
    </>
  );
}
